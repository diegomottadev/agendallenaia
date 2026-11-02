<?php

namespace App\Services\Google;

/**
 * La base de la API de Google Calendar.
 *
 * Igual que `App\Meta\GraphApi`: la URL sale de configuración para que en
 * desarrollo apunte al mock local (`GOOGLE_API_URL`) y la reserva se pueda
 * recorrer entera sin un calendario real. En producción vale
 * `https://www.googleapis.com`.
 */
final class GoogleApi
{
    public static function base(): string
    {
        // `?:` y no el default de `config()`: una variable vacía en el .env llega
        // como cadena vacía y dejaría las llamadas sin host.
        return rtrim((string) config('services.google.api_url') ?: 'https://www.googleapis.com', '/');
    }
}
