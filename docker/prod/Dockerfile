# Imagen de produccion: FrankenPHP (PHP 8.4 + Caddy) con el codigo adentro.
#
# Por que FrankenPHP y no php-fpm + nginx: Caddy viene embebido y saca solo el
# certificado de Let's Encrypt para SERVER_NAME. Meta exige HTTPS para el
# webhook, y asi el VPS no necesita otro contenedor ni certbot.
#
# La misma imagen corre los tres procesos: web, worker de la cola y scheduler.
# Lo que cambia es el `command` en docker-compose.prod.yml.

# --- 1 · assets del panel (Vite) ---------------------------------------------
FROM node:26.13-bookworm-slim AS assets
WORKDIR /build
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# --- 2 · dependencias de PHP sin las de desarrollo ---------------------------
FROM composer:2.8 AS vendor
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# --- 3 · runtime --------------------------------------------------------------
FROM dunglas/frankenphp:1.13.4-php8.4-alpine

# Alpine y no Debian: 387 MB contra 914 MB, con el mismo PHP y las mismas
# extensiones. Pesa en un VPS chico, donde durante un deploy conviven la imagen
# vieja y la nueva.
#
# OWASP A03 · La base se fija por versión exacta (la actualiza Dependabot) y cada
# build aplica los parches de seguridad publicados después de que se armó la
# imagen. `icu-data-full`: Alpine trae solo los datos de ICU en inglés, y `intl`
# necesita los de español.
RUN apk upgrade --no-cache \
    && apk add --no-cache icu-data-full

# Mismas extensiones que la imagen de desarrollo, mas `timezonedb`.
#
# tzdata (T-031): la base de husos que trae el PHP de FrankenPHP queda atras de
# la que exige ZonasHorariasTransversalTest (2026.3 al escribir esto; la base de FrankenPHP
# suele traer una anterior). `timezonedb` es la extension PECL que reemplaza esa base por la
# ultima publicada al momento del build. Sin ella, un turno en un pais que cambio
# su regla por decreto sale corrido una hora y sin ningun error.
RUN install-php-extensions pdo_mysql redis bcmath intl zip opcache pcntl timezonedb

RUN printf '%s\n' \
    'opcache.enable=1' \
    'opcache.validate_timestamps=0' \
    'opcache.memory_consumption=256' \
    'opcache.max_accelerated_files=20000' \
    'expose_php=0' \
    > /usr/local/etc/php/conf.d/zz-produccion.ini \
    && cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

WORKDIR /app
COPY --from=vendor /build /app
COPY --from=assets /build/public/build /app/public/build
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint-agendallena

RUN rm -rf tests e2e .claude docker/mysql \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/entrypoint-agendallena

ENTRYPOINT ["entrypoint-agendallena"]
# Por defecto, el servidor web. El worker y el scheduler lo reemplazan.
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
