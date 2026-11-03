<?php

namespace Tests\Feature;

use App\Conversacion\Estado;
use App\Models\Conversation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Controles OWASP Top 10:2025 que faltaban en el MVP, cada uno con su rojo
 * antes del código (salvo el de A05, que cubre una protección que ya existía).
 *
 * - A02 · Mala configuración: cabeceras de seguridad en toda respuesta.
 * - A06 · Diseño inseguro: el webhook público tenía que calcular un HMAC sobre
 *   cualquier cuerpo, de cualquier tamaño, sin límite de pedidos.
 * - A05 · Inyección: el nombre que escribe el cliente por WhatsApp llega al
 *   panel; tiene que mostrarse como texto, nunca ejecutarse.
 */
class SeguridadOwaspTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = '/api/v1/webhooks/whatsapp';

    // --------------------------------------------- A02 · Cabeceras de seguridad

    public function test_toda_respuesta_web_lleva_las_cabeceras_de_seguridad(): void
    {
        $r = $this->get('/login')->assertOk();

        $r->assertHeader('X-Frame-Options', 'DENY');
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $r->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=()', (string) $r->headers->get('Permissions-Policy'));
    }

    public function test_la_csp_no_permite_scripts_en_linea_ni_que_otro_sitio_nos_enmarque(): void
    {
        $csp = (string) $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\/=]+'/", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", $this->directiva($csp, 'script-src'),
            'Un script en línea inyectado se ejecutaría: es lo que la CSP existe para impedir.');
    }

    public function test_hsts_solo_por_https(): void
    {
        $this->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        // Por HTTP no se manda: el navegador la ignora y en local trabaría el puerto.
        $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_las_respuestas_de_la_api_tambien_llevan_cabeceras(): void
    {
        $this->get(self::WEBHOOK.'?hub_mode=subscribe')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    // ------------------------------------------- A06 · Webhook público acotado

    public function test_el_webhook_rechaza_un_cuerpo_desmedido_antes_de_validar_la_firma(): void
    {
        $cuerpo = str_repeat('a', 300 * 1024);

        $this->call('POST', self::WEBHOOK, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.str_repeat('0', 64),
        ], $cuerpo)->assertStatus(413);
    }

    public function test_el_webhook_limita_los_pedidos_por_minuto(): void
    {
        config()->set('services.meta.webhook_por_minuto', 3);

        foreach (range(1, 3) as $i) {
            // Sin firma válida: 401, pero cuentan igual para el límite.
            $this->postJson(self::WEBHOOK, ['entry' => []])->assertStatus(401);
        }

        $this->postJson(self::WEBHOOK, ['entry' => []])->assertStatus(429);
    }

    // ------------------------------------------------ A05 · XSS en el panel

    public function test_el_nombre_que_escribe_el_cliente_se_muestra_escapado_en_el_panel(): void
    {
        $tenant = Tenant::create([
            'name' => 'Peluquería Sur', 'slug' => 'sur', 'status' => 'active',
            'timezone' => 'America/Argentina/Buenos_Aires',
        ]);
        $duenia = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Ana', 'email' => 'ana@sur.test',
            'password' => 'secreto123', 'role' => Role::Owner,
        ]);
        TenantContext::runAs($tenant->id, fn () => Conversation::create([
            'user_phone' => '5493764278402',
            'user_name' => '<script>alert("xss")</script>',
            'current_state' => Estado::GatheringParams->value,
            'last_interaction_at' => CarbonImmutable::now('UTC'),
        ]));

        $html = $this->actingAs($duenia)->get('/panel/conversaciones')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $html);
    }

    private function directiva(string $csp, string $nombre): string
    {
        foreach (explode(';', $csp) as $parte) {
            if (str_starts_with(trim($parte), $nombre.' ')) {
                return trim($parte);
            }
        }

        return '';
    }
}
