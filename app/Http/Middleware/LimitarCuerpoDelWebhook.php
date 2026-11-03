<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OWASP A06:2025 · El webhook es público: antes de calcular un HMAC sobre el
 * cuerpo, se descarta lo que ningún envío de Meta puede medir.
 *
 * Un lote de Meta trae a lo sumo unas decenas de mensajes de texto y pesa pocos
 * kilobytes; los adjuntos viajan como id, no como bytes. 256 KB deja margen de
 * sobra y le corta a un atacante la vía de hacernos hashear megas por pedido.
 */
class LimitarCuerpoDelWebhook
{
    public const MAXIMO_BYTES = 256 * 1024;

    public function handle(Request $request, Closure $next): Response
    {
        $declarado = (int) $request->headers->get('Content-Length', '0');

        if ($declarado > self::MAXIMO_BYTES || strlen($request->getContent()) > self::MAXIMO_BYTES) {
            abort(413);
        }

        return $next($request);
    }
}
