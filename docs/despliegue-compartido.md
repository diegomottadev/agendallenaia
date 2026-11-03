# Despliegue en un servidor compartido (`agendallenaia.atiende.lat`)

> Para correr AgendaLlena en un droplet que **ya existe** y tiene otros proyectos, detrás de su nginx y con su MySQL.
> Para un droplet propio aprovisionado con Terraform, ver [`despliegue.md`](despliegue.md).

## Cómo queda

```
Internet ──443──► nginx del host (certificado *.atiende.lat)
                    ├─ admin.atiende.lat          → 127.0.0.1:82   (pedidos_platform)
                    ├─ atiende.lat y *.atiende.lat → 127.0.0.1:81   (atiende)
                    ├─ avisos.atiende.lat         → telegram-mcp
                    └─ agendallenaia.atiende.lat  → 127.0.0.1:8083 (AgendaLlena)
                                                       │
                       web · worker · scheduler · redis ┘── red de mysql8 ──► mysql8 (base `agendallena`)
```

| Pieza | Archivo |
| :-- | :-- |
| Servicios | [`docker-compose.compartido.yml`](../docker-compose.compartido.yml): web solo en `127.0.0.1`, sin MySQL propio, con un tope de memoria por contenedor |
| PHP acotado | [`docker/compartido/php.ini`](../docker/compartido/php.ini) |
| nginx | [`docker/compartido/nginx-agendallenaia.conf`](../docker/compartido/nginx-agendallenaia.conf) |
| Base y usuario | [`docker/compartido/crear-base.sql`](../docker/compartido/crear-base.sql) |
| Variables | [`.env.compartido.example`](../.env.compartido.example) |

**Consumo medido en una simulación local** con 1 vCPU: web 34 MB, worker 34 MB, scheduler 35 MB y Redis 8 MB, en total **unos 110 MB de RAM**. La imagen pesa **387 MB** en disco.

**DNS:** no hay que tocar nada. El comodín `*.atiende.lat` de Spaceship ya apunta a este servidor, y el `server_name` exacto le gana a la regex de `atiende` en nginx.

## Paso a paso

### 1. Liberar disco (hacen falta ~1 GB libres)

```bash
journalctl --vacuum-size=50M
apt-get clean
apt-get autoremove --purge -y
docker builder prune -af
find /var/log -type f -name "*.gz" -delete
df -h /
```

### 2. Usuario `deploy` y carpeta de la app

```bash
adduser --disabled-password --gecos "" deploy
usermod -aG docker deploy
install -d -o deploy -g deploy -m 750 /opt/agendallena
install -d -o deploy -g deploy -m 755 /opt/agendallena/storage/framework/cache \
  /opt/agendallena/storage/framework/sessions /opt/agendallena/storage/framework/views \
  /opt/agendallena/storage/logs /opt/agendallena/storage/app
# Clave pública dedicada al CD (ssh-keygen -t ed25519 -f agendallena_deploy en tu máquina):
install -d -o deploy -g deploy -m 700 /home/deploy/.ssh
echo "ssh-ed25519 AAAA... github-actions-deploy" > /home/deploy/.ssh/authorized_keys
chown deploy:deploy /home/deploy/.ssh/authorized_keys && chmod 600 /home/deploy/.ssh/authorized_keys
```

### 3. Red del `mysql8`

```bash
docker inspect mysql8 --format '{{range $k, $v := .NetworkSettings.Networks}}{{$k}}{{"\n"}}{{end}}'
```

El nombre que devuelve va en `MYSQL_NETWORK`. ⚠️ Si es `bridge`, la red por defecto, hay que crear una red propia y conectarle el `mysql8`: `docker network create mysql && docker network connect mysql mysql8`. Así `atiende` y `pedidos` siguen funcionando igual.

### 4. Base y usuario propios

Editá `crear-base.sql` con una clave larga y corré:

```bash
docker exec -i mysql8 mysql -uroot -p < crear-base.sql
```

El usuario `agendallena` solo ve su base; no puede leer las de `atiende` ni `pedidos`.

### 5. `.env`

Completá en tu máquina una copia de [`.env.compartido.example`](../.env.compartido.example): `APP_KEY`, `MYSQL_NETWORK`, `DB_PASSWORD`, Meta y Google. Después subila:

```bash
scp .env.compartido root@137.184.76.211:/tmp/.env
ssh root@137.184.76.211 'install -o deploy -g deploy -m 600 /tmp/.env /opt/agendallena/.env && rm /tmp/.env'
```

⚠️ **Subred:** el compose usa `172.30.83.0/24` y `TRUSTED_PROXIES=172.30.83.1`. Si `docker network ls` muestra una red que se superpone, cambiá las dos cosas juntas.

### 6. nginx

```bash
cp nginx-agendallenaia.conf /etc/nginx/sites-available/agendallenaia.atiende.lat
ln -s /etc/nginx/sites-available/agendallenaia.atiende.lat /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

`nginx -t` antes de recargar: si falla, los otros sitios no se enteran.

### 7. GitHub: entorno `produccion`

| Tipo | Nombre | Valor |
| :-- | :-- | :-- |
| Protección | Required reviewers | Vos |
| Variable | `COMPOSE_FILE` | `docker-compose.compartido.yml` |
| Variable | `DOMAIN` | `agendallenaia.atiende.lat` |
| Variable | `DO_FIREWALL_ID` | *(vacía: este droplet no tiene Cloud Firewall)* |
| Secret | `DO_HOST` | `137.184.76.211` |
| Secret | `DO_SSH_KEY` | La clave **privada** `agendallena_deploy` |
| Secret | `DO_SSH_KNOWN_HOSTS` | Salida de `ssh-keyscan -t ed25519 137.184.76.211` |

Y en el repositorio: `DEPLOY_ENABLED=true`.

### 8. Primer deploy

**Actions → deploy → Run workflow.** El workflow construye la imagen, la escanea con Trivy, la publica en GHCR, pide tu aprobación y corre [`desplegar.sh`](../scripts/deploy/desplegar.sh): dump previo, levantar, migrar, reiniciar la cola, chequeo de salud y rollback si algo falla.

En el primer arranque el worker se reinicia unas veces hasta que la migración crea las tablas. Es esperable y pasa una sola vez.

### 9. Meta y Google

- **Meta:** registrar `https://agendallenaia.atiende.lat/api/v1/webhooks/whatsapp` con el `verify_token` del tenant.
- **Google:** agregar `https://agendallenaia.atiende.lat/api/v1/oauth/google/callback` como URI de redirección.

## Verificado en local, simulando el servidor

nginx con certificado, el compose compartido y un MySQL 8.4 en su propia red:

| Comprobación | Resultado |
| :-- | :-- |
| HTTPS por nginx, con HTTP/2 | 200 |
| La app sabe que el pedido fue HTTPS | HSTS presente y formularios con `https://agendallenaia.atiende.lat` |
| `/up` | 200 |
| `/dev/whatsapp` (simulador) | 404 |
| Webhook sin firma | 401 |
| Migraciones contra la base compartida | OK |

## Riesgos aceptados

- **Un proceso dentro del servidor puede falsear `X-Forwarded-Proto`** hablándole directo a `127.0.0.1:8083`. Desde internet no se puede, porque el puerto escucha solo en loopback.
- **Recursos compartidos:** si `atiende` o `pedidos` se comen la memoria, AgendaLlena también sufre. Los `mem_limit` del compose impiden lo contrario. Sin alertas de DigitalOcean configuradas, conviene revisar `free -m` y `docker stats` de vez en cuando.
- **El `mysql8` es un punto único** para los tres proyectos. El dump diario del droplet propio no existe acá: agregar un cron con `mysqldump` de la base `agendallena` hacia otro lugar.
