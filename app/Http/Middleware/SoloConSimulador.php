<?php

namespace App\Http\Middleware;

use App\Dev\SimuladorHabilitado;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las rutas `/dev/*` del simulador de WhatsApp existen solo en desarrollo.
 *
 * Contesta 404 y no 403: en un entorno donde el simulador está apagado, la
 * ruta no tiene que delatar que existe. **En producción se corta siempre**,
 * aunque `META_SIMULADOR` haya quedado en `true` por un `.env` copiado de más:
 * un endpoint que firma webhooks con el secreto de Meta no puede estar abierto.
 */
class SoloConSimulador
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! SimuladorHabilitado::ahora()) {
            abort(404);
        }

        return $next($request);
    }
}
