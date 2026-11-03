<?php

namespace Tests\Unit;

use App\Conversacion\Bienvenida;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * AC-16.3 · La salida de emergencia del cliente.
 *
 * Oráculo especificado: el criterio pide reconocer la palabra «escrita como sea»
 * desde un celular. Partición: palabras que reinician, variantes de escritura de
 * esas palabras, y textos que se parecen pero no lo son.
 */
class PalabraDeReinicioTest extends TestCase
{
    /** @return array<string,array{?string,bool}> */
    public static function casos(): array
    {
        return [
            'menu' => ['menu', true],
            'MENÚ! con acento y signo' => ['MENÚ!', true],
            'con espacios' => ['  Inicio  ', true],
            'volver' => ['volver', true],
            'empezar' => ['Empezar.', true],
            'dentro de una frase no reinicia' => ['quiero volver mañana', false],
            'parecida pero distinta' => ['menudo', false],
            'vacío' => ['', false],
            'nulo (un botón sin texto)' => [null, false],
            'solo emojis' => ['🔙🔙', false],
        ];
    }

    #[DataProvider('casos')]
    public function test_reconoce_solo_las_palabras_de_reinicio(?string $texto, bool $esperado): void
    {
        $this->assertSame($esperado, Bienvenida::esPalabraDeReinicio($texto));
    }
}
