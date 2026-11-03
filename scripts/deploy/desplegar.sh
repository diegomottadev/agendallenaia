#!/usr/bin/env bash
# Despliegue en el droplet, ejecutado por el usuario `deploy` desde el CD.
#
#   IMAGE_NAME=ghcr.io/... IMAGE_TAG=<sha> [COMPOSE_FILE=...] ./desplegar.sh
#
# Dos modos, según COMPOSE_FILE:
#   - docker-compose.prod.yml (por defecto): droplet propio, Caddy con HTTPS y
#     MySQL en el mismo compose.
#   - docker-compose.compartido.yml: detrás del nginx del host y con el mysql8
#     que ya existe en el servidor.
#
# Pasos: dump previo → pull → up → migraciones → reinicio de la cola →
# chequeo de salud. Si la app no responde sana, vuelve a la imagen anterior y
# sale con error para que el workflow quede en rojo.
#
# ⚠️ El rollback revierte la imagen, no las migraciones: cada migración tiene
# que ser compatible con la versión anterior del código (expand/contract).
set -Eeuo pipefail

cd /opt/agendallena

: "${IMAGE_NAME:?falta IMAGE_NAME}"
: "${IMAGE_TAG:?falta IMAGE_TAG}"

ARCHIVO="${COMPOSE_FILE:-docker-compose.prod.yml}"
COMPOSE=(docker compose -f "$ARCHIVO")
ESTADO=.image_tag
ANTERIOR="$(cat "$ESTADO" 2>/dev/null || true)"
# Una variable que no está (DOMAIN no existe en el modo compartido) devuelve
# vacío. Sin el `|| true`, con pipefail el grep fallido mataba el script en
# silencio antes de empezar.
leer() { { grep -E "^$1=" .env || true; } | tail -1 | cut -d= -f2-; }
DOMINIO="$(leer DOMAIN)"
PUERTO_LOCAL="$(leer PUERTO_LOCAL)"

log() { printf '[deploy %s] %s\n' "$(date -u +%H:%M:%S)" "$*"; }

sano() {
  # Droplet propio: por HTTPS con el dominio real (valida Caddy y el
  # certificado). Compartido: por el puerto local, que es lo que ve nginx.
  local url=(--resolve "$DOMINIO:443:127.0.0.1" "https://$DOMINIO/up")
  if [ "$ARCHIVO" = docker-compose.compartido.yml ]; then
    url=("http://127.0.0.1:${PUERTO_LOCAL:-8083}/up")
  fi
  for _ in $(seq 1 20); do
    if curl -fsS --max-time 5 "${url[@]}" -o /dev/null; then
      return 0
    fi
    sleep 3
  done
  return 1
}

dump_previo() {
  if [ "$ARCHIVO" = docker-compose.compartido.yml ]; then
    # Con el usuario propio de AgendaLlena, que solo ve su base.
    docker exec -e MYSQL_PWD="$(leer DB_PASSWORD)" "$(leer DB_HOST)" \
      mysqldump --single-transaction --no-tablespaces -u"$(leer DB_USERNAME)" "$(leer DB_DATABASE)"
  elif "${COMPOSE[@]}" ps --status running --services 2>/dev/null | grep -qx mysql; then
    # Comillas simples a propósito: las variables se expanden dentro del contenedor.
    # shellcheck disable=SC2016
    "${COMPOSE[@]}" exec -T mysql \
      sh -c 'exec mysqldump --single-transaction -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
  fi
}

desplegar() {
  export IMAGE_NAME IMAGE_TAG="$1"
  "${COMPOSE[@]}" pull web worker scheduler
  "${COMPOSE[@]}" up -d --remove-orphans
}

log "imagen anterior: ${ANTERIOR:-ninguna} · nueva: $IMAGE_TAG"

log "dump previo a las migraciones"
dump_previo | gzip > pre-deploy.sql.gz
chmod 600 pre-deploy.sql.gz

desplegar "$IMAGE_TAG"
"${COMPOSE[@]}" exec -T web php artisan migrate --force
# El worker carga el código una sola vez: hay que avisarle que hay versión nueva.
"${COMPOSE[@]}" exec -T web php artisan queue:restart

if sano; then
  echo "$IMAGE_TAG" > "$ESTADO"
  docker image prune -f >/dev/null
  log "ok: $IMAGE_TAG responde sano"
  exit 0
fi

log "la versión nueva no responde sana"
if [ -n "$ANTERIOR" ]; then
  log "rollback a $ANTERIOR"
  desplegar "$ANTERIOR"
  if sano; then
    log "rollback ok"
  else
    log "⚠️ el rollback tampoco responde: revisar a mano"
  fi
fi
exit 1
