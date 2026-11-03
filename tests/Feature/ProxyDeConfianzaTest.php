<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Despliegue detrás de un nginx que termina el HTTPS (servidor compartido).
 *
 * nginx le habla a la app por HTTP plano y avisa del HTTPS original con
 * `X-Forwarded-Proto`. Si Laravel no confía en ese proxy, cree que todo es
 * HTTP: genera links `http://`, no manda HSTS y las cookies `secure` se rompen.
 *
 * Pero la cabecera solo vale si viene del proxy configurado: de cualquier otro
 * origen se ignora, o un cliente podría hacerse pasar por HTTPS (OWASP A05).
 */
class ProxyDeConfianzaTest extends TestCase
{
    /** En los tests el pedido llega desde 127.0.0.1, como desde un nginx local. */
    private function pedidoReenviadoPorHttps()
    {
        return $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'agendallenaia.atiende.lat',
            'X-Forwarded-Port' => '443',
        ])->get('http://localhost/login');
    }

    public function test_detras_del_proxy_configurado_la_app_sabe_que_el_pedido_fue_https(): void
    {
        config()->set('trustedproxy.proxies', '127.0.0.1');

        $r = $this->pedidoReenviadoPorHttps()->assertOk();

        // ORACULO: HSTS solo sale cuando el pedido es seguro (CabecerasDeSeguridad).
        $r->assertHeader('Strict-Transport-Security');
        $this->assertStringContainsString('action="https://agendallenaia.atiende.lat/login"', $r->getContent(),
            'El formulario apunta a http: el navegador lo bloquearía como contenido mixto.');
    }

    public function test_sin_proxy_configurado_la_cabecera_reenviada_se_ignora(): void
    {
        config()->set('trustedproxy.proxies', null);

        $r = $this->pedidoReenviadoPorHttps()->assertOk();

        $r->assertHeaderMissing('Strict-Transport-Security');
        $this->assertStringNotContainsString('https://agendallenaia.atiende.lat', $r->getContent(),
            'Un cliente cualquiera pudo hacerse pasar por HTTPS y cambiar el host de los links.');
    }

    public function test_un_proxy_que_no_es_el_configurado_no_es_de_confianza(): void
    {
        config()->set('trustedproxy.proxies', '10.9.9.9');

        $this->pedidoReenviadoPorHttps()->assertOk()->assertHeaderMissing('Strict-Transport-Security');
    }
}
