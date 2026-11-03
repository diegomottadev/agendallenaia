<?php

/*
 * Proxies de confianza: quién puede decirle a la app, con `X-Forwarded-*`, que
 * el pedido original fue HTTPS y a qué host iba.
 *
 * - Droplet propio (Caddy dentro de FrankenPHP termina el HTTPS): vacío.
 * - Servidor compartido detrás de nginx: la IP desde la que nginx llega al
 *   contenedor, por ejemplo la del gateway de la red de Docker. Ver
 *   docs/despliegue-compartido.md.
 *
 * Nunca `*` con el puerto publicado a internet: cualquiera podría hacerse pasar
 * por HTTPS o cambiar el host de los links (OWASP A05).
 *
 * Lo lee Illuminate\Http\Middleware\TrustProxies cuando no hay proxies fijados
 * en código. Lista separada por comas.
 */
return [
    'proxies' => env('TRUSTED_PROXIES'),
];
