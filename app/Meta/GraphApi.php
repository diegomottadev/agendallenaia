<?php

namespace App\Meta;

/**
 * La base de la Graph API de Meta.
 *
 * Existe para que la URL salga de configuración y no de un literal repetido en
 * cada llamada: en desarrollo apunta al mock local (`META_GRAPH_URL`) y ningún
 * mensaje llega a un teléfono real. En producción no se toca y vale
 * `https://graph.facebook.com`.
 */
final class GraphApi
{
    public static function base(): string
    {
        // `?:` y no el default de `config()`: una `META_GRAPH_URL=` vacía en el
        // .env llega como cadena vacía, no como ausente, y dejaría las llamadas
        // sin host.
        return rtrim((string) config('services.meta.graph_url') ?: 'https://graph.facebook.com', '/');
    }
}
