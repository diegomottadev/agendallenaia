<?php

namespace Tests\Feature;

use App\Conversacion\Estado;
use App\Jobs\ProcessMessageJob;
use App\Models\Conversation;
use App\Models\Integration;
use App\Models\Tenant;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use App\Meta\MetaRateLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AC-09.3 + AC-09.4 · El reintento tras un rate limit de Meta tiene que
 * **contestar**, no solo no fallar.
 *
 * Lo destapó el E2E `caminos-de-error.spec.ts`: el primer intento aplica la
 * transición `IDLE → GATHERING_PARAMS` y recién después manda la bienvenida.
 * Meta responde 130429, el job se relanza, y en el reintento la conversación ya
 * avanzó: el mismo «Hola» cae en un estado sin intérprete y el cliente no recibe
 * nada. Exactamente el silencio que AC-09.4 prohíbe.
 *
 * Los tests existentes probaban que el rate limit **se lanza** para que la cola
 * reintente; ninguno corría el reintento.
 */
class ReintentoTrasRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE_NUMBER_ID = '1053554814514902';

    private const TELEFONO = '5493764278402';

    private const TZ = 'America/Argentina/Buenos_Aires';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 10:00', self::TZ));
        config()->set('services.meta.phone_number_id', self::PHONE_NUMBER_ID);

        $this->tenant = Tenant::create([
            'name' => 'Peluquería Sur', 'slug' => 'piloto',
            'status' => 'active', 'timezone' => self::TZ,
        ]);

        Integration::create([
            'tenant_id' => $this->tenant->id, 'provider' => Integration::PROVIDER_META_WHATSAPP,
            'account_identifier' => self::PHONE_NUMBER_ID, 'access_token' => 'meta',
            'settings' => ['verify_token' => 'tok'], 'status' => 'connected',
        ]);

        Integration::create([
            'tenant_id' => $this->tenant->id, 'provider' => Integration::PROVIDER_GOOGLE_CALENDAR,
            'account_identifier' => 'duenio@peluqueria.com', 'access_token' => 'ya29',
            'refresh_token' => '1//r', 'expires_at' => CarbonImmutable::parse('2027-01-01', 'UTC'),
            'status' => 'connected',
        ]);

        TenantContext::set($this->tenant->id);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        TenantContext::forget();
        parent::tearDown();
    }

    /** @return array<string,mixed> */
    private function payloadDeTexto(string $texto): array
    {
        return ['entry' => [['changes' => [[
            'field' => 'messages',
            'value' => [
                'metadata' => ['phone_number_id' => self::PHONE_NUMBER_ID],
                'messages' => [[
                    'from' => self::TELEFONO, 'id' => 'wamid.'.uniqid('', true),
                    'type' => 'text', 'text' => ['body' => $texto],
                ]],
            ],
        ]]]]];
    }

    /** Lo que efectivamente salió hacia Meta, como texto plano para buscar. @return array<int,string> */
    private function enviosAMeta(): array
    {
        $out = [];
        foreach (Http::recorded() as [$req, $res]) {
            if (str_contains($req->url(), 'graph.facebook.com')) {
                $out[] = json_encode($req->data(), JSON_UNESCAPED_UNICODE);
            }
        }

        return $out;
    }

    public function test_el_reintento_tras_un_rate_limit_le_manda_la_bienvenida_al_cliente(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::sequence()
            ->push(['error' => ['code' => 130429]], 400)
            ->push(['messages' => [['id' => 'wamid.ok']]])
            ->push(['messages' => [['id' => 'wamid.ok2']]]),
        ]);

        // La cola reintenta el mismo job, con el mismo payload y el mismo `wamid`.
        $payload = $this->payloadDeTexto('Hola');

        try {
            (new ProcessMessageJob($payload))->handle();
            $this->fail('El primer intento tenía que lanzar el rate limit para que la cola reintente.');
        } catch (MetaRateLimit) {
        }

        (new ProcessMessageJob($payload))->handle();

        $envios = $this->enviosAMeta();
        $this->assertCount(2, $envios, 'El reintento no le mandó nada al cliente: quedó sin respuesta.');
        $this->assertStringContainsString('Gracias por escribirnos', $envios[1],
            'Lo que salió en el reintento no es la bienvenida que el primer intento no pudo entregar.');
    }
}
