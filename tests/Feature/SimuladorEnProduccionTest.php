<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El simulador de WhatsApp en un despliegue de demo.
 *
 * En producción sigue cortado por defecto (lo prueba `SimuladorDeWhatsAppTest`).
 * Solo lo abre un permiso explícito, `META_SIMULADOR_EN_PRODUCCION`, pensado
 * para un servidor de demostración sin clientes ni Meta reales.
 */
class SimuladorEnProduccionTest extends TestCase
{
    use RefreshDatabase;

    private const NUMERO = '990000000000001';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.meta.simulador', true);
        config()->set('services.meta.app_secret', 'secreto-de-prueba');
        config()->set('services.meta.simulador_webhook_url', 'http://app.test/api/v1/webhooks/whatsapp');

        $tenant = Tenant::create([
            'name' => 'Peluquería Sur', 'slug' => 'sim-prod',
            'status' => 'active', 'timezone' => 'America/Argentina/Buenos_Aires',
        ]);

        Integration::create([
            'tenant_id' => $tenant->id,
            'provider' => Integration::PROVIDER_META_WHATSAPP,
            'account_identifier' => self::NUMERO,
            'access_token' => 'token-simulado',
            'settings' => ['verify_token' => 'tok'],
            'status' => 'connected',
        ]);

        $this->app->detectEnvironment(fn () => 'production');
    }

    protected function tearDown(): void
    {
        TenantContext::forget();
        parent::tearDown();
    }

    public function test_con_el_permiso_explicito_el_simulador_existe_en_produccion(): void
    {
        config()->set('services.meta.simulador_en_produccion', true);

        $this->get('/dev/whatsapp')->assertOk();
        $this->postJson('/api/dev/meta/v21.0/'.self::NUMERO.'/messages')->assertOk();
    }

    public function test_con_el_permiso_explicito_se_puede_preparar_en_produccion(): void
    {
        config()->set('services.meta.simulador_en_produccion', true);

        $this->artisan('dev:whatsapp:preparar')->assertSuccessful();
    }

    public function test_el_permiso_solo_no_alcanza_si_el_simulador_esta_apagado(): void
    {
        config()->set('services.meta.simulador_en_produccion', true);
        config()->set('services.meta.simulador', false);

        $this->get('/dev/whatsapp')->assertNotFound();
        $this->artisan('dev:whatsapp:preparar')->assertFailed();
    }
}
