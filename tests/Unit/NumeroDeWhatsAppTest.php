<?php

namespace Tests\Unit;

use App\Meta\NumeroDeWhatsApp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Normalización de teléfonos, sin base ni HTTP.
 *
 * Oráculo: lo verificado contra Meta el 2026-08-19 (docblock de la clase). Meta
 * acepta el envío a un móvil argentino **sin** el 9 y devuelve el `wa_id`
 * **con** el 9. Los valores esperados están calculados a mano, no copiados de
 * una corrida.
 */
class NumeroDeWhatsAppTest extends TestCase
{
    /** @return array<string,array{string,string}> */
    public static function paraEnviar(): array
    {
        return [
            'wa_id argentino: se saca el 9' => ['5493764278402', '543764278402'],
            'ya sin el 9: no se toca' => ['543764278402', '543764278402'],
            'AMBA con el 9' => ['5491122223333', '541122223333'],
            'otro país: el 9 es parte del número' => ['56912345678', '56912345678'],
            'borde: 12 dígitos con 54 9 no se recorta' => ['549376427840', '549376427840'],
        ];
    }

    #[DataProvider('paraEnviar')]
    public function test_para_enviar(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, NumeroDeWhatsApp::paraEnviar($entrada));
    }

    /** @return array<string,array{string,string}> */
    public static function paraGuardar(): array
    {
        return [
            'sin el 9: se agrega para coincidir con el wa_id' => ['543764278402', '5493764278402'],
            'con el 9: queda igual' => ['5493764278402', '5493764278402'],
            'otro país: no se toca' => ['56912345678', '56912345678'],
            'borde: 11 dígitos argentinos no se completan' => ['54376427840', '54376427840'],
        ];
    }

    #[DataProvider('paraGuardar')]
    public function test_para_guardar(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, NumeroDeWhatsApp::paraGuardar($entrada));
    }

    /** Sanitización: así lo escribe una persona, no como lo manda Meta. @return array<string,array{string}> */
    public static function escriturasHumanas(): array
    {
        return [
            'con + y espacios' => ['+54 9 376 427-8402'],
            'con paréntesis' => ['(54) 9 3764 278402'],
            'sin el 9 y con guiones' => ['54-3764-278402'],
            'con espacios alrededor' => ['  5493764278402  '],
        ];
    }

    #[DataProvider('escriturasHumanas')]
    public function test_cualquier_escritura_humana_es_el_mismo_telefono(string $escrito): void
    {
        $this->assertTrue(NumeroDeWhatsApp::sonElMismo($escrito, '5493764278402'));
        $this->assertSame('543764278402', NumeroDeWhatsApp::paraEnviar($escrito));
    }

    public function test_dos_telefonos_distintos_no_son_el_mismo(): void
    {
        $this->assertFalse(NumeroDeWhatsApp::sonElMismo('5493764278402', '5493764278403'));
    }

    public function test_texto_sin_digitos_queda_vacio_y_no_coincide_con_un_telefono(): void
    {
        $this->assertSame('', NumeroDeWhatsApp::paraEnviar("'; DROP TABLE conversations; --"));
        $this->assertFalse(NumeroDeWhatsApp::sonElMismo('abc', '5493764278402'));
    }
}
