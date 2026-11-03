<?php

namespace App\Http\Controllers\Dev;

use App\Dev\SimuladorDeMeta;
use App\Http\Controllers\Controller;
use App\Meta\PlantillasDeWhatsApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Contesta en lugar de la Graph API de Meta (ver `SimuladorDeMeta`).
 *
 * Imita **la forma** de las respuestas que el código lee, no el comportamiento
 * completo de Meta: un `wamid` al enviar, y la lista de plantillas aprobadas.
 * Las plantillas vuelven `APPROVED` y no `PENDING` para que el recordatorio
 * t-24h se pueda probar sin esperar una aprobación que acá no existe.
 */
class MetaFalsaController extends Controller
{
    public function enviarMensaje(): JsonResponse
    {
        /*
         * Una falla pedida desde el simulador se consume en el primer envío:
         * es lo que permite ver el reintento de la cola después de un 130429,
         * que con una falla permanente nunca saldría bien.
         */
        $falla = Cache::pull(SimuladorDeMeta::CLAVE_FALLA);

        if ($falla === SimuladorDeMeta::FALLA_RATE_LIMIT) {
            return response()->json(['error' => [
                'message' => '(#130429) Rate limit hit', 'type' => 'OAuthException', 'code' => 130429,
            ]], 400);
        }

        if ($falla === SimuladorDeMeta::FALLA_RECHAZO) {
            return response()->json(['error' => [
                'message' => '(#131026) Message undeliverable', 'type' => 'OAuthException', 'code' => 131026,
            ]], 400);
        }

        return response()->json([
            'messaging_product' => 'whatsapp',
            'contacts' => [['input' => request('to'), 'wa_id' => request('to')]],
            'messages' => [['id' => 'wamid.SIM_OUT_'.Str::random(20)]],
        ]);
    }

    public function listarPlantillas(): JsonResponse
    {
        return response()->json(['data' => array_map(
            fn (string $nombre) => ['name' => $nombre, 'status' => 'APPROVED', 'id' => 'sim-'.$nombre],
            PlantillasDeWhatsApp::NOMBRES,
        )]);
    }

    public function crearPlantilla(): JsonResponse
    {
        return response()->json([
            'id' => 'sim-'.request('name'),
            'status' => 'APPROVED',
            'category' => request('category', 'UTILITY'),
        ]);
    }
}
