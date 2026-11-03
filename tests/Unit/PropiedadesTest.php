<?php

namespace Tests\Unit;

use App\Conversacion\Bienvenida;
use App\Conversacion\Estado;
use App\Conversacion\TablaDeTransiciones;
use App\Conversacion\Transicion;
use App\Meta\NumeroDeWhatsApp;
use Eris\Generator;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property-based testing (Eris) sobre las clases puras del dominio.
 *
 * Un test por ejemplos dice «con ESTA entrada sale ESTO». Una propiedad dice
 * «con CUALQUIER entrada de esta forma, vale esto», y Eris genera cien entradas
 * por corrida y, si alguna falla, la achica (shrinking) hasta el contraejemplo
 * mínimo.
 *
 * Oráculo de todas: **invariante**. Ninguna compara contra un valor copiado de
 * una corrida; cada una dice de dónde sale en su docblock.
 *
 * Reproducir un contraejemplo: Eris imprime la semilla al fallar.
 *   docker compose exec -T -e ERIS_SEED=<semilla> -e DB_DATABASE=agendallena_test_m7 \
 *     app php artisan test --filter=PropiedadesTest
 */
class PropiedadesTest extends TestCase
{
    use TestTrait;

    // ---------------------------------------------------------------------
    // Generadores
    // ---------------------------------------------------------------------

    /**
     * Teléfonos «como los escribe alguien»: un prefijo de país (Argentina con y
     * sin el 9, y vecinos donde el 9 o el largo significan otra cosa), de 0 a 14
     * dígitos más, y separadores humanos intercalados.
     *
     * El largo variable importa: los cortes del código están en 12 y 13 dígitos,
     * y una propiedad que solo ve números de 13 no los cruza nunca.
     */
    private function telefonos(): Generator
    {
        return Generators::map(
            function (array $partes): string {
                [$prefijo, $digitos, $separador] = $partes;

                return $prefijo.$separador.implode($separador, str_split(implode('', $digitos) ?: '', 3));
            },
            Generators::tuple(
                Generators::elements('54', '549', '+54 9 ', '+54 ', '56', '52', '521', '55', '598', '1', ''),
                Generators::vector(
                    // vector() exige largo fijo: se combina con oneOf para variar.
                    11,
                    Generators::elements('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'),
                ),
                Generators::elements('', ' ', '-', '(', ')', '.'),
            ),
        );
    }

    /** Lo mismo pero con largo variable de 0 a 14 dígitos. */
    private function telefonosDeLargoVariable(): Generator
    {
        return Generators::map(
            fn (array $p): string => $p[0].substr($p[1], 0, $p[2]),
            Generators::tuple(
                Generators::elements('54', '549', '+54 9 ', '56', '52', '55', ''),
                Generators::map(
                    fn (array $d): string => implode('', $d),
                    Generators::vector(14, Generators::elements('0', '1', '2', '3', '4', '5', '6', '7', '8', '9')),
                ),
                Generators::choose(0, 14),
            ),
        );
    }

    /**
     * Bytes arbitrarios, incluido UTF-8 inválido y el byte nulo. Es lo que puede
     * llegar en el `text.body` de un webhook manipulado.
     */
    private function bytesArbitrarios(): Generator
    {
        return Generators::map(
            fn (array $bytes): string => implode('', array_map('chr', $bytes)),
            Generators::seq(Generators::choose(0, 255)),
        );
    }

    /** Texto «de celular»: Unicode real, emojis, RTL, acentos, espacios raros. */
    private function textoUnicode(): Generator
    {
        return Generators::map(
            fn (array $trozos): string => implode('', $trozos),
            Generators::seq(Generators::elements(
                'a', 'Z', 'ñ', 'Ñ', 'ú', 'Ú', 'ü', '😀', '🔙', 'مرحبا', "\u{200B}", "\u{00A0}",
                "\t", "\n", ' ', '!', '¿', '0', '9', 'menu', 'MENÚ', 'volver',
            )),
        );
    }

    private function cualquierTexto(): Generator
    {
        return Generators::oneOf(
            Generators::string(),
            $this->bytesArbitrarios(),
            $this->textoUnicode(),
            $this->telefonos(),
            $this->telefonosDeLargoVariable(),
        );
    }

    // ---------------------------------------------------------------------
    // NumeroDeWhatsApp
    // ---------------------------------------------------------------------

    /**
     * P1 · `paraGuardar` es idempotente.
     *
     * Oráculo: es la forma **canónica** (docblock de la clase: «para guardar y
     * comparar»). Una forma canónica que cambia al re-normalizarla no es canónica:
     * dos filas del mismo cliente quedarían con teléfonos distintos según cuántas
     * veces pasó el dato por el normalizador.
     */
    public function test_para_guardar_es_idempotente(): void
    {
        $this->forAll($this->cualquierTexto())
            ->then(function (string $x): void {
                $una = NumeroDeWhatsApp::paraGuardar($x);

                $this->assertSame($una, NumeroDeWhatsApp::paraGuardar($una), "Entrada: ".json_encode($x));
            });
    }

    /**
     * P2 · Guardar no cambia a dónde se envía: `paraEnviar(paraGuardar(x)) === paraEnviar(x)`.
     *
     * Oráculo diferencial: el teléfono se guarda en forma canónica y se le manda
     * a Meta desde lo guardado. Si normalizar al guardar cambiara el destino, el
     * recordatorio t-24h saldría a un número distinto del que escribió el cliente.
     */
    public function test_guardar_no_cambia_el_destino_del_envio(): void
    {
        $this->forAll($this->cualquierTexto())
            ->then(function (string $x): void {
                $this->assertSame(
                    NumeroDeWhatsApp::paraEnviar($x),
                    NumeroDeWhatsApp::paraEnviar(NumeroDeWhatsApp::paraGuardar($x)),
                    'Entrada: '.json_encode($x),
                );
            });
    }

    /**
     * P3 · `paraEnviar` nunca lanza, devuelve solo dígitos y nunca agrega dígitos.
     *
     * Oráculo invariante: lo que se le manda a Meta es un número (solo dígitos) y
     * normalizar a la forma de envío solo puede **sacar** el 9, nunca inventar
     * uno. Con bytes arbitrarios, porque el `from` de un webhook es entrada no
     * confiable.
     */
    public function test_para_enviar_nunca_lanza_y_solo_devuelve_digitos(): void
    {
        $this->forAll($this->cualquierTexto())
            ->then(function (string $x): void {
                $enviado = NumeroDeWhatsApp::paraEnviar($x);
                $digitosDeEntrada = preg_replace('/\D+/', '', $x) ?? '';

                $this->assertMatchesRegularExpression('/^\d*$/', $enviado);
                $this->assertLessThanOrEqual(strlen($digitosDeEntrada), strlen($enviado));
            });
    }

    // ---------------------------------------------------------------------
    // Bienvenida
    // ---------------------------------------------------------------------

    /**
     * P4 · `esPalabraDeReinicio` nunca lanza con cualquier texto, y es inmune a
     * mayúsculas, espacios y signos alrededor de una palabra de reinicio.
     *
     * Oráculo especificado (AC-16.3): la palabra se reconoce «escrita como sea»
     * desde un celular. Las palabras salen del criterio, no de la constante.
     */
    public function test_palabra_de_reinicio_nunca_lanza_y_tolera_la_escritura(): void
    {
        $this->forAll($this->cualquierTexto())
            ->then(function (string $x): void {
                $this->assertIsBool(Bienvenida::esPalabraDeReinicio($x));
            });

        $this->forAll(
            Generators::elements('menu', 'menú', 'inicio', 'empezar', 'reiniciar', 'volver'),
            Generators::elements('', ' ', '  ', "\t", '¡', '!', '.', '...', '¿', '?', '*'),
            Generators::elements('', ' ', '!!', '.', '?', ' :)', "\n"),
            Generators::bool(),
        )->then(function (string $palabra, string $antes, string $despues, bool $mayusculas): void {
            $escrita = $antes.($mayusculas ? mb_strtoupper($palabra) : $palabra).$despues;

            $this->assertTrue(Bienvenida::esPalabraDeReinicio($escrita), 'Escrita: '.json_encode($escrita));
        });
    }

    // ---------------------------------------------------------------------
    // TablaDeTransiciones
    // ---------------------------------------------------------------------

    /**
     * P5 · Para cualquier estado y evento: el destino es `null` o un `Estado`,
     * `eventosValidosDesde` coincide con `destino`, y **un estado terminal nunca
     * sale a `ERROR_FALLBACK`**.
     *
     * Oráculo: comodín 2 del catálogo de flujos (§5) y su decisión — si el turno
     * ya está agendado, que Google falle después no le cambia nada al cliente.
     * Mandar un `BOOKED` a `ERROR_FALLBACK` «liberaría lo reservado», es decir,
     * un turno real.
     */
    public function test_ningun_estado_terminal_cae_en_error_fallback(): void
    {
        $this->forAll(
            Generators::elements(Estado::cases()),
            Generators::elements(Transicion::cases()),
        )->then(function (Estado $desde, Transicion $evento): void {
            $destino = TablaDeTransiciones::destino($desde, $evento);

            $this->assertTrue($destino === null || $destino instanceof Estado);

            $this->assertSame(
                $destino !== null,
                in_array($evento, TablaDeTransiciones::eventosValidosDesde($desde), true),
                "{$desde->value} --{$evento->value}--> eventosValidosDesde no coincide con destino",
            );

            if ($desde->esTerminal()) {
                $this->assertNotSame(
                    Estado::ErrorFallback,
                    $destino,
                    "{$desde->value} --{$evento->value}--> ERROR_FALLBACK: un turno real no puede liberarse por un fallo posterior",
                );
            }
        });
    }
}
