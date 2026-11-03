<?php

namespace Tests\Unit;

use App\Conversacion\Estado;
use App\Conversacion\TablaDeTransiciones;
use App\Conversacion\Transicion;
use PHPUnit\Framework\TestCase;

/**
 * Invariantes de la máquina de estados, para **todos** los estados a la vez.
 *
 * Oráculo de invariante: no se compara contra un valor puntual sino contra una
 * propiedad que tiene que valer siempre. Son las que, rotas, dejan al cliente
 * atrapado o mudo, y ningún test por estado las ve todas juntas.
 */
class TablaDeTransicionesTest extends TestCase
{
    public function test_ningun_estado_es_un_callejon_sin_salida(): void
    {
        foreach (Estado::cases() as $estado) {
            $this->assertNotEmpty(TablaDeTransiciones::eventosValidosDesde($estado),
                "Desde {$estado->value} no hay ningún evento válido: el cliente queda atrapado.");
        }
    }

    public function test_desde_cualquier_estado_se_puede_volver_al_inicio(): void
    {
        foreach (Estado::cases() as $estado) {
            if ($estado === Estado::Idle) {
                continue;
            }
            $this->assertSame(Estado::Idle, TablaDeTransiciones::destino($estado, Transicion::Reinicia),
                "Desde {$estado->value} la palabra de reinicio no vuelve al inicio (AC-16.3).");
        }
    }

    public function test_todo_estado_es_alcanzable_desde_idle(): void
    {
        $visitados = [Estado::Idle->value => true];
        $pendientes = [Estado::Idle];

        while ($pendientes) {
            $actual = array_pop($pendientes);
            foreach (TablaDeTransiciones::eventosValidosDesde($actual) as $evento) {
                $destino = TablaDeTransiciones::destino($actual, $evento);
                if (! isset($visitados[$destino->value])) {
                    $visitados[$destino->value] = true;
                    $pendientes[] = $destino;
                }
            }
        }

        $inalcanzables = array_diff(array_map(fn (Estado $e) => $e->value, Estado::cases()), array_keys($visitados));
        $this->assertSame([], array_values($inalcanzables), 'Hay estados declarados a los que ningún camino lleva.');
    }

    public function test_un_turno_ya_agendado_no_vuelve_a_pedir_datos(): void
    {
        foreach ([Estado::Booked, Estado::Confirmed, Estado::Cancelled] as $terminal) {
            $this->assertNull(TablaDeTransiciones::destino($terminal, Transicion::ParametrosCompletos));
            $this->assertNull(TablaDeTransiciones::destino($terminal, Transicion::FallaTercero),
                "Una falla de un tercero no puede mandar {$terminal->value} a ERROR_FALLBACK: se perdería el turno.");
        }
    }
}
