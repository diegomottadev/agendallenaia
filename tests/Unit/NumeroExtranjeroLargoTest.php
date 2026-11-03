<?php

namespace Tests\Unit;

use App\Meta\NumeroDeWhatsApp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Salió de Infection (mutation testing), no de un ticket.
 *
 * Mutante que mata: `ReturnRemoval` en `NumeroDeWhatsApp::paraGuardar`, sobre
 * el `return $digitos;` de «fuera de Argentina no se toca». Sin ese return, un
 * número extranjero de 12 dígitos o más cae en la rama del móvil argentino sin
 * el 9 y sale como `549` + el resto: un brasileño o un mexicano quedan guardados
 * como un argentino inexistente.
 *
 * Sobrevivía porque el único caso extranjero de `NumeroDeWhatsAppTest` es
 * chileno, de 11 dígitos, y el corte de la rama está en 12. Brasil y México
 * —mercados del segmento— tienen 13.
 *
 * Oráculo especificado: docblock de `NumeroDeWhatsApp` («el 9 solo sobra en
 * Argentina … en el resto de los países el noveno dígito es parte del número»).
 * Los números están armados a mano con el plan de numeración de cada país.
 */
class NumeroExtranjeroLargoTest extends TestCase
{
    /** @return array<string,array{string}> */
    public static function extranjerosLargos(): array
    {
        return [
            'Brasil, móvil de São Paulo (13 dígitos)' => ['5511987654321'],
            'México, móvil con el 1 histórico (13 dígitos)' => ['5215512345678'],
            'México, móvil actual (12 dígitos)' => ['525512345678'],
            'Uruguay con separadores (11 dígitos)' => ['+598 94 123 456'],
        ];
    }

    #[DataProvider('extranjerosLargos')]
    public function test_un_numero_extranjero_largo_se_guarda_sin_tocar(string $numero): void
    {
        $soloDigitos = preg_replace('/\D+/', '', $numero);

        $this->assertSame($soloDigitos, NumeroDeWhatsApp::paraGuardar($numero));
    }

    #[DataProvider('extranjerosLargos')]
    public function test_un_numero_extranjero_largo_se_envia_sin_tocar(string $numero): void
    {
        $soloDigitos = preg_replace('/\D+/', '', $numero);

        $this->assertSame($soloDigitos, NumeroDeWhatsApp::paraEnviar($numero));
    }
}
