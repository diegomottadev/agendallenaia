<?php

namespace App\Http\Controllers\Dev;

use App\Dev\SimuladorDeMeta;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Integration;
use App\Models\Message;
use App\Recordatorios\PlantillaRecordatorio24h;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * `/dev/whatsapp` · un chat de WhatsApp de mentira contra el bot de verdad.
 *
 * Lo que se escribe acá viaja como webhook firmado al endpoint real; lo que el
 * bot contesta se lee de `messages`. Ver `SimuladorDeMeta`.
 */
class SimuladorWhatsAppController extends Controller
{
    public function index(): View
    {
        return view('dev.whatsapp', [
            'numeros' => Integration::query()
                ->where('provider', Integration::PROVIDER_META_WHATSAPP)
                ->with('tenant:id,name')
                ->get()
                ->map(fn (Integration $i) => [
                    'numero' => $i->account_identifier,
                    'negocio' => $i->tenant?->name ?? $i->tenant_id,
                ]),
            'graphUrl' => config('services.meta.graph_url'),
            // La plantilla viaja sin el texto de sus botones (lo tiene Meta), así
            // que el simulador los rotula con la acción de cada índice.
            'botonesPlantilla' => array_map('ucfirst', PlantillaRecordatorio24h::BOTONES),
        ]);
    }

    /** El historial de la conversación de `from` con el negocio de `numero`. */
    public function mensajes(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'numero' => ['required', 'string'],
            'from' => ['required', 'string'],
        ]);

        $integracion = SimuladorDeMeta::integracionDe($datos['numero']);

        // Cross-tenant a propósito: el simulador no tiene sesión de panel, el
        // tenant sale del número igual que en la ingesta del webhook.
        $conversacion = $integracion === null ? null : Conversation::withoutTenantScope()
            ->where('tenant_id', $integracion->tenant_id)
            ->where('user_phone', $datos['from'])
            ->first();

        if ($conversacion === null) {
            return response()->json(['estado' => null, 'mensajes' => []]);
        }

        $mensajes = Message::withoutTenantScope()
            ->where('conversation_id', $conversacion->id)
            ->orderBy('id')
            ->get(['id', 'direction', 'type', 'content', 'payload', 'created_at']);

        return response()->json([
            'estado' => $conversacion->current_state,
            'mensajes' => $mensajes,
        ]);
    }

    public function enviar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'numero' => ['required', 'string'],
            'from' => ['required', 'string', 'regex:/^\d{8,15}$/'],
            'nombre' => ['required', 'string', 'max:60'],
            'tipo' => ['required', Rule::in(['texto', 'button', 'list', 'plantilla'])],
            'texto' => ['required_if:tipo,texto', 'nullable', 'string', 'max:4096'],
            'id' => ['required_unless:tipo,texto', 'nullable', 'string'],
            'titulo' => ['required_unless:tipo,texto', 'nullable', 'string'],
        ]);

        if (SimuladorDeMeta::integracionDe($datos['numero']) === null) {
            return response()->json(['error' => 'Ningún negocio atiende ese número. Corré `php artisan dev:whatsapp:preparar`.'], 422);
        }

        $payload = match ($datos['tipo']) {
            'texto' => SimuladorDeMeta::payloadDeTexto($datos['numero'], $datos['from'], $datos['nombre'], $datos['texto']),
            'plantilla' => SimuladorDeMeta::payloadDeBotonDePlantilla($datos['numero'], $datos['from'], $datos['nombre'], $datos['id'], $datos['titulo']),
            default => SimuladorDeMeta::payloadDeRespuesta($datos['numero'], $datos['from'], $datos['nombre'], $datos['tipo'], $datos['id'], $datos['titulo']),
        };

        $respuesta = SimuladorDeMeta::enviarAlWebhook($payload);

        return response()->json(['webhook' => $respuesta->status()], $respuesta->successful() ? 200 : 502);
    }

    /** Programa una falla para el próximo envío del bot a "Meta". */
    public function falla(Request $request): JsonResponse
    {
        $datos = $request->validate(['modo' => ['nullable', Rule::in(SimuladorDeMeta::FALLAS)]]);

        empty($datos['modo'])
            ? Cache::forget(SimuladorDeMeta::CLAVE_FALLA)
            : Cache::put(SimuladorDeMeta::CLAVE_FALLA, $datos['modo'], now()->addHour());

        return response()->json(['modo' => $datos['modo'] ?? null]);
    }
}
