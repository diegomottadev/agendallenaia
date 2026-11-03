<?php

namespace Tests\Feature;

use App\Dev\SimuladorDeMeta;
use App\Jobs\ProcessMessageJob;
use App\Meta\GraphApi;
use App\Meta\MetaAdapter;
use App\Meta\PlantillasDeWhatsApp;
use App\Models\Conversation;
use App\Models\Integration;
use App\Models\Message;
use App\Models\Tenant;
use App\Services\Google\GoogleApi;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * El simulador de WhatsApp para desarrollo.
 *
 * Lo que importa probar es la frontera: que el mock reemplaza a Meta **solo**
 * cuando se lo pide y nunca en producción, y que lo que simula pasa por el
 * mismo camino que un mensaje real (firma, cola, historial).
 */
class SimuladorDeWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private const NUMERO = '990000000000001';

    private const CLIENTE = '5491100000001';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.meta.simulador', true);
        config()->set('services.meta.app_secret', 'secreto-de-prueba');
        config()->set('services.meta.simulador_webhook_url', 'http://app.test/api/v1/webhooks/whatsapp');

        $this->tenant = Tenant::create([
            'name' => 'Peluquería Sur', 'slug' => 'sim',
            'status' => 'active', 'timezone' => 'America/Argentina/Buenos_Aires',
        ]);

        Integration::create([
            'tenant_id' => $this->tenant->id,
            'provider' => Integration::PROVIDER_META_WHATSAPP,
            'account_identifier' => self::NUMERO,
            'access_token' => 'token-simulado',
            'settings' => ['verify_token' => 'tok'],
            'status' => 'connected',
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::forget();
        parent::tearDown();
    }

    // ------------------------------------------------------- la URL de Meta

    public function test_sin_url_configurada_la_graph_api_es_la_de_meta(): void
    {
        foreach ([null, ''] as $vacia) {
            config()->set('services.meta.graph_url', $vacia);

            $this->assertSame('https://graph.facebook.com', GraphApi::base());
        }
    }

    public function test_el_adapter_envia_a_la_base_configurada_y_no_a_meta(): void
    {
        config()->set('services.meta.graph_url', 'http://mock.test/api/dev/meta/');
        Http::fake(['mock.test/*' => Http::response(['messages' => [['id' => 'wamid.X']]])]);

        $conversacion = TenantContext::runAs($this->tenant->id, fn () => Conversation::create([
            'user_phone' => self::CLIENTE, 'current_state' => Conversation::ESTADO_INICIAL,
        ]));
        $meta = Integration::where('account_identifier', self::NUMERO)->first();

        TenantContext::runAs($this->tenant->id, fn () => (new MetaAdapter($meta))->enviarTexto($conversacion, 'hola'));

        Http::assertSent(fn ($r) => $r->url() === 'http://mock.test/api/dev/meta/v21.0/'.self::NUMERO.'/messages');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'graph.facebook.com'));
    }

    // ------------------------------------------------------- nunca fuera de dev

    public function test_con_el_simulador_apagado_las_rutas_no_existen(): void
    {
        config()->set('services.meta.simulador', false);

        $this->get('/dev/whatsapp')->assertNotFound();
        $this->postJson('/api/dev/meta/v21.0/'.self::NUMERO.'/messages')->assertNotFound();
    }

    public function test_en_produccion_las_rutas_no_existen_aunque_el_simulador_este_encendido(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->get('/dev/whatsapp')->assertNotFound();
        $this->postJson('/api/dev/meta/v21.0/'.self::NUMERO.'/messages')->assertNotFound();
        $this->artisan('dev:whatsapp', ['texto' => 'hola'])->assertFailed();
    }

    // ------------------------------------------------------- el mock de Meta

    public function test_el_mock_contesta_un_wamid_como_meta(): void
    {
        $this->postJson('/api/dev/meta/v21.0/'.self::NUMERO.'/messages', ['to' => self::CLIENTE])
            ->assertOk()
            ->assertJsonPath('messages.0.id', fn ($id) => str_starts_with($id, 'wamid.'));
    }

    public function test_una_falla_programada_se_consume_en_el_primer_envio(): void
    {
        Cache::put(SimuladorDeMeta::CLAVE_FALLA, SimuladorDeMeta::FALLA_RATE_LIMIT);

        $this->postJson('/api/dev/meta/v21.0/'.self::NUMERO.'/messages')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 130429);

        $this->postJson('/api/dev/meta/v21.0/'.self::NUMERO.'/messages')->assertOk();
    }

    public function test_el_mock_lista_las_plantillas_del_producto_aprobadas(): void
    {
        $data = $this->getJson('/api/dev/meta/v21.0/waba-1/message_templates')->assertOk()->json('data');

        $this->assertEqualsCanonicalizing(PlantillasDeWhatsApp::NOMBRES, array_column($data, 'name'));
        $this->assertSame(['APPROVED'], array_values(array_unique(array_column($data, 'status'))));
    }

    // ------------------------------------------------------- la entrada simulada

    public function test_el_webhook_simulado_pasa_la_firma_real_y_llega_a_la_cola(): void
    {
        Queue::fake();
        $cuerpo = json_encode(SimuladorDeMeta::payloadDeTexto(self::NUMERO, self::CLIENTE, 'Ana', 'hola'));

        $this->call('POST', '/api/v1/webhooks/whatsapp', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => SimuladorDeMeta::firmar($cuerpo),
        ], content: $cuerpo)->assertOk();

        Queue::assertPushed(ProcessMessageJob::class);
    }

    public function test_enviar_desde_el_chat_manda_el_webhook_firmado(): void
    {
        Http::fake(['app.test/*' => Http::response('', 200)]);

        $this->postJson('/dev/whatsapp/enviar', [
            'numero' => self::NUMERO, 'from' => self::CLIENTE, 'nombre' => 'Ana',
            'tipo' => 'list', 'id' => 'slot-1', 'titulo' => 'Lunes 10:00',
        ])->assertOk();

        Http::assertSent(function ($r) {
            $mensaje = $r->data()['entry'][0]['changes'][0]['value']['messages'][0];

            return $r->url() === 'http://app.test/api/v1/webhooks/whatsapp'
                && $r->header('X-Hub-Signature-256')[0] === SimuladorDeMeta::firmar($r->body())
                && $mensaje['interactive']['list_reply']['id'] === 'slot-1';
        });
    }

    public function test_enviar_a_un_numero_sin_negocio_no_manda_nada(): void
    {
        Http::fake();

        $this->postJson('/dev/whatsapp/enviar', [
            'numero' => '123', 'from' => self::CLIENTE, 'nombre' => 'Ana', 'tipo' => 'texto', 'texto' => 'hola',
        ])->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_el_chat_lee_el_historial_de_la_conversacion(): void
    {
        TenantContext::runAs($this->tenant->id, function () {
            $c = Conversation::create(['user_phone' => self::CLIENTE, 'current_state' => Conversation::ESTADO_INICIAL]);
            Message::create(['conversation_id' => $c->id, 'direction' => Message::ENTRANTE, 'type' => 'text', 'content' => 'hola', 'whatsapp_message_id' => 'wamid.A']);
            Message::create(['conversation_id' => $c->id, 'direction' => Message::SALIENTE, 'type' => 'text', 'content' => '¡Hola!', 'whatsapp_message_id' => 'wamid.B']);
        });

        $this->getJson('/dev/whatsapp/mensajes?numero='.self::NUMERO.'&from='.self::CLIENTE)
            ->assertOk()
            ->assertJsonPath('estado', Conversation::ESTADO_INICIAL)
            ->assertJsonPath('mensajes.0.content', 'hola')
            ->assertJsonPath('mensajes.1.content', '¡Hola!');
    }

    // ------------------------------------------------------- preparar

    public function test_preparar_le_da_numero_simulado_solo_a_quien_no_tiene(): void
    {
        $otro = Tenant::create(['name' => 'Estética Norte', 'slug' => 'norte', 'status' => 'active', 'timezone' => 'UTC']);

        $this->artisan('dev:whatsapp:preparar')->assertSuccessful();
        $this->artisan('dev:whatsapp:preparar')->assertSuccessful();

        $this->assertSame(1, Integration::where('tenant_id', $otro->id)->where('provider', Integration::PROVIDER_META_WHATSAPP)->count());
        $this->assertSame(self::NUMERO, Integration::where('tenant_id', $this->tenant->id)->where('provider', Integration::PROVIDER_META_WHATSAPP)->value('account_identifier'));
    }

    // ------------------------------------------------------- el mock de Google

    public function test_sin_url_configurada_la_api_de_google_es_la_real(): void
    {
        foreach ([null, ''] as $vacia) {
            config()->set('services.google.api_url', $vacia);

            $this->assertSame('https://www.googleapis.com', GoogleApi::base());
        }
    }

    public function test_un_evento_creado_en_el_mock_queda_ocupado_en_freebusy(): void
    {
        $evento = ['start' => ['dateTime' => '2026-11-03T10:00:00-03:00'], 'end' => ['dateTime' => '2026-11-03T10:30:00-03:00']];

        $this->withToken('cal-a')->postJson('/api/dev/google/calendar/v3/calendars/primary/events', ['id' => 'evt1'] + $evento)
            ->assertOk()->assertJsonPath('id', 'evt1');
        // Reintento con el mismo id: Google contesta 409 y el código lo toma como éxito.
        $this->withToken('cal-a')->postJson('/api/dev/google/calendar/v3/calendars/primary/events', ['id' => 'evt1'] + $evento)
            ->assertStatus(409);

        $ventana = ['timeMin' => '2026-11-03T00:00:00Z', 'timeMax' => '2026-11-04T00:00:00Z'];

        $this->withToken('cal-a')->postJson('/api/dev/google/calendar/v3/freeBusy', $ventana)
            ->assertJsonPath('calendars.primary.busy.0.start', '2026-11-03T13:00:00+00:00');

        // Otro token es otro calendario: los negocios simulados no comparten agenda.
        $this->withToken('cal-b')->postJson('/api/dev/google/calendar/v3/freeBusy', $ventana)
            ->assertJsonCount(0, 'calendars.primary.busy');

        // Cancelar libera el horario, y el evento sigue visible como `cancelled`.
        $this->withToken('cal-a')->deleteJson('/api/dev/google/calendar/v3/calendars/primary/events/evt1')->assertNoContent();
        $this->withToken('cal-a')->deleteJson('/api/dev/google/calendar/v3/calendars/primary/events/evt1')->assertStatus(410);
        $this->withToken('cal-a')->postJson('/api/dev/google/calendar/v3/freeBusy', $ventana)
            ->assertJsonCount(0, 'calendars.primary.busy');
        $this->withToken('cal-a')->getJson('/api/dev/google/calendar/v3/calendars/primary/events/evt1')
            ->assertJsonPath('status', 'cancelled');
    }

    public function test_el_mock_de_google_tampoco_existe_con_el_simulador_apagado(): void
    {
        config()->set('services.meta.simulador', false);

        $this->postJson('/api/dev/google/calendar/v3/freeBusy')->assertNotFound();
    }

    public function test_preparar_tambien_le_da_un_calendario_simulado(): void
    {
        $this->artisan('dev:whatsapp:preparar')->assertSuccessful();

        $google = Integration::where('tenant_id', $this->tenant->id)
            ->where('provider', Integration::PROVIDER_GOOGLE_CALENDAR)->first();

        $this->assertNotNull($google);
        $this->assertSame('connected', $google->status);
        $this->assertNull($google->expires_at);
    }
}
