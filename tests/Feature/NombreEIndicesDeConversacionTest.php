<?php

namespace Tests\Feature;

use App\Conversacion\Estado;
use App\Jobs\ProcessMessageJob;
use App\Models\Conversation;
use App\Models\Integration;
use App\Models\Tenant;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tres defectos que salieron de la revisión para la entrega 2.
 *
 * 1. **«Sin nombre» en el panel.** El nombre que da el cliente quedaba solo en
 *    `context_data.nombre` y en el turno; `conversations.user_name`, que es lo
 *    que lista el panel, no se escribía nunca. Lo destapó el E2E.
 * 2. **Conversaciones duplicadas.** La ingesta resuelve por `(tenant_id,
 *    user_phone)` con `firstOrCreate`, que no es atómico: dos webhooks
 *    simultáneos del mismo teléfono podían crear dos filas. La garantía tiene
 *    que ser la base, como ya lo es para `messages.whatsapp_message_id`.
 * 3. **`notification_logs` sin índice para el panel de fallidos**, que filtra
 *    por `(tenant_id, status)`. El único índice por tenant era el de la FK.
 */
class NombreEIndicesDeConversacionTest extends TestCase
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

    public function test_el_nombre_que_da_el_cliente_queda_en_la_conversacion(): void
    {
        Http::fake([
            'www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['primary' => ['busy' => []]]]),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ok']]]),
        ]);

        $conversacion = Conversation::create([
            'user_phone' => self::TELEFONO,
            'current_state' => Estado::GatheringParams->value,
            'context_data' => ['esperando' => 'nombre'],
            'last_interaction_at' => now(),
        ]);

        (new ProcessMessageJob($this->payloadDeTexto('  María López ')))->handle();

        $this->assertSame('María López', $conversacion->fresh()->user_name,
            'El panel lista `user_name`: si no se escribe, la fila dice «Sin nombre».');
    }

    public function test_no_puede_haber_dos_conversaciones_del_mismo_telefono_en_un_tenant(): void
    {
        $fila = [
            'user_phone' => self::TELEFONO,
            'current_state' => Estado::Idle->value,
            'last_interaction_at' => now(),
        ];

        Conversation::create($fila);

        $this->expectException(UniqueConstraintViolationException::class);
        Conversation::create($fila);
    }

    public function test_notification_logs_tiene_un_indice_por_tenant_y_estado(): void
    {
        $columnas = array_map(
            fn (array $indice) => $indice['columns'],
            Schema::getIndexes('notification_logs'),
        );

        $this->assertContains(['tenant_id', 'status'], $columnas,
            'El panel de recordatorios fallidos filtra por (tenant_id, status).');
    }
}
