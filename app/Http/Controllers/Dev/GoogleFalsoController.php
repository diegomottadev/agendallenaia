<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Contesta en lugar de Google Calendar durante el desarrollo (`GOOGLE_API_URL`).
 *
 * **Tiene estado, a diferencia del mock de Meta:** los eventos que crea el bot
 * se guardan en caché y vuelven como ocupados en `freeBusy`. Sin eso el mock
 * ofrecería otra vez un horario recién reservado y la demo mostraría justo el
 * bug que el producto existe para evitar.
 *
 * Cada calendario se identifica por el token de la integración: dos negocios
 * simulados no comparten agenda. Imita la forma de las respuestas que el código
 * lee, no toda la API.
 */
class GoogleFalsoController extends Controller
{
    public function freeBusy(Request $request): JsonResponse
    {
        $desde = CarbonImmutable::parse((string) $request->input('timeMin'));
        $hasta = CarbonImmutable::parse((string) $request->input('timeMax'));

        $ocupado = collect($this->eventos($request))
            ->filter(fn (array $e) => $e['status'] !== 'cancelled')
            ->filter(fn (array $e) => CarbonImmutable::parse($e['start']['dateTime']) < $hasta
                && CarbonImmutable::parse($e['end']['dateTime']) > $desde)
            ->map(fn (array $e) => [
                'start' => CarbonImmutable::parse($e['start']['dateTime'])->utc()->toRfc3339String(),
                'end' => CarbonImmutable::parse($e['end']['dateTime'])->utc()->toRfc3339String(),
            ])
            ->values();

        return response()->json([
            'kind' => 'calendar#freeBusy',
            'timeMin' => $desde->utc()->toRfc3339String(),
            'timeMax' => $hasta->utc()->toRfc3339String(),
            'calendars' => ['primary' => ['busy' => $ocupado]],
        ]);
    }

    public function crearEvento(Request $request): JsonResponse
    {
        $eventos = $this->eventos($request);
        $id = (string) ($request->input('id') ?: Str::lower(Str::random(26)));

        // Google contesta 409 si el id de idempotencia ya existe: es lo que
        // `CalendarioDeGoogle` usa para no duplicar un evento en un reintento.
        if (isset($eventos[$id])) {
            return response()->json(['error' => ['code' => 409, 'message' => 'The requested identifier already exists.']], 409);
        }

        $evento = array_merge($request->only(['summary', 'description', 'start', 'end', 'extendedProperties']), [
            'id' => $id,
            'status' => 'confirmed',
            'htmlLink' => 'https://calendar.google.com/calendar/event?eid='.$id,
            'created' => now()->utc()->toRfc3339String(),
        ]);

        $eventos[$id] = $evento;
        $this->guardar($request, $eventos);

        return response()->json($evento);
    }

    public function listarEventos(Request $request): JsonResponse
    {
        $conBorrados = $request->boolean('showDeleted');

        $items = collect($this->eventos($request))
            ->filter(fn (array $e) => $conBorrados || $e['status'] !== 'cancelled')
            ->values();

        return response()->json(['kind' => 'calendar#events', 'items' => $items]);
    }

    public function verEvento(Request $request, string $id): JsonResponse
    {
        $evento = $this->eventos($request)[$id] ?? null;

        return $evento === null
            ? response()->json(['error' => ['code' => 404, 'message' => 'Not Found']], 404)
            : response()->json($evento);
    }

    public function borrarEvento(Request $request, string $id): Response|JsonResponse
    {
        $eventos = $this->eventos($request);

        if (! isset($eventos[$id]) || $eventos[$id]['status'] === 'cancelled') {
            return response()->json(['error' => ['code' => 410, 'message' => 'Resource has been deleted']], 410);
        }

        // Google no borra: marca `cancelled`, y un GET posterior lo sigue viendo.
        $eventos[$id]['status'] = 'cancelled';
        $this->guardar($request, $eventos);

        return response()->noContent();
    }

    /** @return array<string,array<string,mixed>> */
    private function eventos(Request $request): array
    {
        return Cache::get($this->clave($request), []);
    }

    /** @param array<string,array<string,mixed>> $eventos */
    private function guardar(Request $request, array $eventos): void
    {
        Cache::put($this->clave($request), $eventos, now()->addDays(30));
    }

    private function clave(Request $request): string
    {
        return 'dev:google:eventos:'.hash('sha256', (string) $request->bearerToken());
    }
}
