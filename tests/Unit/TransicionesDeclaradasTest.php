<?php

namespace Tests\Unit;

use App\Conversacion\Estado;
use App\Conversacion\TablaDeTransiciones;
use App\Conversacion\Transicion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Salió de Infection (mutation testing), no de un ticket.
 *
 * `TablaDeTransicionesTest` prueba propiedades del grafo —sin callejones, todo
 * alcanzable, se puede volver al inicio—, y por eso **ninguna transición
 * puntual** estaba fijada en `tests/Unit`: Infection borró, una por una, las
 * del camino feliz (`ArrayItemRemoval`, `ArrayItem`) y el comodín de
 * inactividad (`Ternary`, `ReturnRemoval`), y la testsuite Unit siguió verde.
 *
 * Oráculo especificado, por fila:
 * - Matriz del catálogo: `03-flujos/05-estados-flujo-conversacional.md` §3.
 * - `GATHERING_PARAMS → SELECTING_SLOT` directo: §2, `CALCULATING_QUOTE` fuera
 *   del MVP (cotizador diferido).
 * - Nombre en `GATHERING_PARAMS`: decisión cerrada de T-007 (RF-A3).
 * - `ERROR_FALLBACK → IDLE`: §5.
 * - Inactividad: §3 («expiración 30 min → IDLE»), AC-11.3 (un re-agendamiento
 *   abandonado expira), y §4 (la pausa no expira).
 *
 * ⚠️ Las dos salidas desde `CONFIRMED` (cancelar y re-agendar) **no figuran en la
 * matriz del catálogo**, que solo las dibuja desde `BOOKED`. Se fijan acá como
 * test de caracterización del comportamiento acordado en T-042 (un turno
 * confirmado se puede mover o cancelar desde el recordatorio), marcado así para
 * que nadie lo lea como especificado por el catálogo.
 */
class TransicionesDeclaradasTest extends TestCase
{
    /** @return array<string,array{Estado,Transicion,Estado}> */
    public static function caminoFeliz(): array
    {
        return [
            'IDLE · mensaje inicial' => [Estado::Idle, Transicion::MensajeInicial, Estado::GatheringParams],
            'GATHERING · pide nombre (ciclo)' => [Estado::GatheringParams, Transicion::PideNombre, Estado::GatheringParams],
            'GATHERING · nombre recibido (ciclo)' => [Estado::GatheringParams, Transicion::NombreRecibido, Estado::GatheringParams],
            'GATHERING · parámetros completos' => [Estado::GatheringParams, Transicion::ParametrosCompletos, Estado::SelectingSlot],
            'SELECTING_SLOT · slot elegido' => [Estado::SelectingSlot, Transicion::SlotElegido, Estado::SlotSelected],
            'SLOT_SELECTED · reserva confirmada' => [Estado::SlotSelected, Transicion::ReservaConfirmada, Estado::Booked],
            'BOOKED · confirma' => [Estado::Booked, Transicion::ConfirmaAsistencia, Estado::Confirmed],
            'BOOKED · cancela' => [Estado::Booked, Transicion::Cancela, Estado::Cancelled],
            'BOOKED · re-agendar' => [Estado::Booked, Transicion::PideReagendar, Estado::Rescheduled],
            '⚠️ CONFIRMED · cancela (caracterización)' => [Estado::Confirmed, Transicion::Cancela, Estado::Cancelled],
            '⚠️ CONFIRMED · re-agendar (caracterización)' => [Estado::Confirmed, Transicion::PideReagendar, Estado::Rescheduled],
            'RESCHEDULED · parámetros completos' => [Estado::Rescheduled, Transicion::ParametrosCompletos, Estado::SelectingSlot],
            'PAUSED_HUMAN · termina la pausa' => [Estado::PausedHuman, Transicion::TerminaPausa, Estado::Idle],
            'ERROR_FALLBACK · fallback enviado' => [Estado::ErrorFallback, Transicion::FallbackEnviado, Estado::Idle],
        ];
    }

    #[DataProvider('caminoFeliz')]
    public function test_cada_transicion_declarada_lleva_a_su_destino(Estado $desde, Transicion $evento, Estado $esperado): void
    {
        $this->assertSame($esperado, TablaDeTransiciones::destino($desde, $evento));
    }

    /** @return array<string,array{Estado,?Estado}> */
    public static function inactividad(): array
    {
        return [
            // En curso: hay un flujo a medias que se pierde → vuelve a IDLE.
            'GATHERING_PARAMS expira' => [Estado::GatheringParams, Estado::Idle],
            'SELECTING_SLOT expira' => [Estado::SelectingSlot, Estado::Idle],
            'SLOT_SELECTED expira' => [Estado::SlotSelected, Estado::Idle],
            'RESCHEDULED expira (AC-11.3)' => [Estado::Rescheduled, Estado::Idle],
            // No en curso: no hay nada que expirar.
            'IDLE no expira' => [Estado::Idle, null],
            'BOOKED no expira (borraría el rastro del turno)' => [Estado::Booked, null],
            'CONFIRMED no expira' => [Estado::Confirmed, null],
            'CANCELLED no expira' => [Estado::Cancelled, null],
            'PAUSED_HUMAN no expira (§4)' => [Estado::PausedHuman, null],
            'ERROR_FALLBACK no expira' => [Estado::ErrorFallback, null],
        ];
    }

    #[DataProvider('inactividad')]
    public function test_la_inactividad_solo_expira_lo_que_esta_en_curso(Estado $desde, ?Estado $esperado): void
    {
        $this->assertSame($esperado, TablaDeTransiciones::destino($desde, Transicion::Inactividad));
    }
}
