<?php

namespace App\Dev;

use App\Models\Integration;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Hace de Meta durante el desarrollo, en las dos direcciones.
 *
 * **Entrada** · arma el mismo webhook que manda Meta cuando un cliente escribe,
 * lo firma con `META_APP_SECRET` y lo manda por HTTP al endpoint real. No llama
 * al job directo a propósito: así el mensaje simulado pasa por la firma
 * (`VerifyMetaSignature`), la cola y el worker, igual que uno de verdad. Lo
 * único falso es Meta.
 *
 * **Salida** · `MetaFalsaController` contesta en lugar de la Graph API cuando
 * `META_GRAPH_URL` apunta a `/api/dev/meta`. Lo que el bot "envía" queda en la
 * tabla `messages` porque `MetaAdapter` ya lo persiste: el simulador lo lee de
 * ahí y no necesita un registro propio.
 *
 * ⚠️ Solo para desarrollo. Las rutas que lo exponen pasan por `SoloConSimulador`.
 */
final class SimuladorDeMeta
{
    /** El próximo envío falla una vez con este modo. Se consume al usarse. */
    public const CLAVE_FALLA = 'dev:meta:proxima-falla';

    public const FALLA_RATE_LIMIT = 'rate_limit';

    public const FALLA_RECHAZO = 'rechazo';

    public const FALLAS = [self::FALLA_RATE_LIMIT, self::FALLA_RECHAZO];

    /** @return array<string,mixed> */
    public static function payloadDeTexto(string $numero, string $from, string $nombre, string $texto): array
    {
        return self::envolver($numero, $from, $nombre, [
            'type' => 'text',
            'text' => ['body' => $texto],
        ]);
    }

    /**
     * El cliente toca un botón o una fila de lista de un mensaje interactivo.
     *
     * @param  string  $origen  `button` o `list`: el tipo del interactivo que se tocó.
     * @return array<string,mixed>
     */
    public static function payloadDeRespuesta(string $numero, string $from, string $nombre, string $origen, string $id, string $titulo): array
    {
        $tipo = $origen === 'list' ? 'list_reply' : 'button_reply';

        return self::envolver($numero, $from, $nombre, [
            'type' => 'interactive',
            'interactive' => ['type' => $tipo, $tipo => ['id' => $id, 'title' => $titulo]],
        ]);
    }

    /**
     * El cliente toca un botón *quick reply* de una plantilla (el recordatorio
     * t-24h). Meta lo manda con otra forma que un interactivo: `type: button`.
     *
     * @return array<string,mixed>
     */
    public static function payloadDeBotonDePlantilla(string $numero, string $from, string $nombre, string $payload, string $texto): array
    {
        return self::envolver($numero, $from, $nombre, [
            'type' => 'button',
            'button' => ['payload' => $payload, 'text' => $texto],
        ]);
    }

    /** La firma `X-Hub-Signature-256` que pone Meta sobre el cuerpo crudo. */
    public static function firmar(string $cuerpo): string
    {
        return 'sha256='.hash_hmac('sha256', $cuerpo, (string) config('services.meta.app_secret'));
    }

    /**
     * Manda el webhook al endpoint real, firmado.
     *
     * @param  array<string,mixed>  $payload
     */
    public static function enviarAlWebhook(array $payload): Response
    {
        $cuerpo = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return Http::withHeaders(['X-Hub-Signature-256' => self::firmar($cuerpo)])
            ->withBody($cuerpo, 'application/json')
            ->timeout(10)
            ->post((string) config('services.meta.simulador_webhook_url'));
    }

    /** La integración de WhatsApp que atiende ese número, si existe. */
    public static function integracionDe(string $numero): ?Integration
    {
        return Integration::query()
            ->where('provider', Integration::PROVIDER_META_WHATSAPP)
            ->where('account_identifier', $numero)
            ->first();
    }

    /**
     * @param  array<string,mixed>  $mensaje
     * @return array<string,mixed>
     */
    private static function envolver(string $numero, string $from, string $nombre, array $mensaje): array
    {
        return ['object' => 'whatsapp_business_account', 'entry' => [[
            'id' => 'SIMULADO',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => ['display_phone_number' => $numero, 'phone_number_id' => $numero],
                    'contacts' => [['profile' => ['name' => $nombre], 'wa_id' => $from]],
                    'messages' => [array_merge([
                        'from' => $from,
                        'id' => 'wamid.SIM_IN_'.Str::random(20),
                        'timestamp' => (string) now()->getTimestamp(),
                    ], $mensaje)],
                ],
            ]],
        ]]];
    }
}
