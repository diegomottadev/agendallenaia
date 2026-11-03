#!/usr/bin/env bash
# Despliegue en el droplet, ejecutado por el usuario `deploy` desde el CD.
#
#   IMAGE_NAME=ghcr.io/... IMAGE_TAG=<sha> ./desplegar.sh
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

COMPOSE=(docker compose -f docker-compose.prod.yml)
ESTADO=.image_tag
ANTERIOR="$(cat "$ESTADO" 2>/dev/null || true)"
DOMINIO="$(grep -E '^DOMAIN=' .env | cut -d= -f2-)"

log() { printf '[deploy %s] %s\n' "$(date -u +%H:%M:%S)" "$*"; }

sano() {
  # Por HTTPS contra el propio servidor, con el dominio real: valida Caddy,
  # el certificado y que Laravel arranque (/up es la ruta de salud).
  for _ in $(seq 1 20); do
    if curl -fsS --max-time 5 --resolve "$DOMINIO:443:127.0.0.1" "https://$DOMINIO/up" -o /dev/null; then
      return 0
    fi
    sleep 3
  done
  return 1
}

desplegar() {
  export IMAGE_NAME IMAGE_TAG="$1"
  "${COMPOSE[@]}" pull web worker scheduler
  "${COMPOSE[@]}" up -d --remove-orphans
}

log "imagen anterior: ${ANTERIOR:-ninguna} · nueva: $IMAGE_TAG"

if "${COMPOSE[@]}" ps --status running --services 2>/dev/null | grep -qx mysql; then
  log "dump previo a las migraciones"
  # Comillas simples a propósito: las variables se expanden dentro del contenedor.
  # shellcheck disable=SC2016
  "${COMPOSE[@]}" exec -T mysql \
    sh -c 'exec mysqldump --single-transaction -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip > pre-deploy.sql.gz
  chmod 600 pre-deploy.sql.gz
fi

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
