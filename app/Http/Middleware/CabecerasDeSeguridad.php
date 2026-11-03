<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * OWASP A02:2025 · Cabeceras de seguridad en **toda** respuesta, web y API.
 *
 * - **CSP con nonce:** solo corren los scripts propios y los que llevan el nonce
 *   de esta respuesta. Un `<script>` inyectado —por ejemplo en el nombre que un
 *   cliente escribe por WhatsApp— no se ejecuta aunque el escape fallara.
 *   `style-src` admite `'unsafe-inline'`: Vue fija estilos en línea y el riesgo
 *   de un estilo inyectado es mucho menor que el de un script.
 * - **`frame-ancestors 'none'` + `X-Frame-Options`:** nadie puede enmarcar el
 *   panel para un clickjacking sobre «Pausar el bot» o «Marcar asistencia».
 * - **HSTS solo por HTTPS:** por HTTP el navegador la ignora, y mandarla en local
 *   no aporta nada.
 */
class CabecerasDeSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        // Antes de `$next`: las vistas imprimen el nonce al renderizarse.
        $nonce = Vite::useCspNonce();

        $respuesta = $next($request);

        $cabeceras = $respuesta->headers;
        $cabeceras->set('X-Frame-Options', 'DENY');
        $cabeceras->set('X-Content-Type-Options', 'nosniff');
        $cabeceras->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $cabeceras->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $cabeceras->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]));

        if ($request->secure()) {
            $cabeceras->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $respuesta;
    }
}
