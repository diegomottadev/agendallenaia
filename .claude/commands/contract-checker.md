---
description: Compara el contrato OpenAPI publicado en el readme de entrega contra las rutas, validaciones y respuestas reales de Laravel, campo por campo, y propone characterization tests para cada diferencia.
argument-hint: "[ruta del readme con el OpenAPI, por defecto ../agendallenaia/readme.md] [endpoint a acotar, ej. /webhooks/whatsapp]"
---

# Rol

Actuá como revisor de contratos de API de **AgendaLlena** (Laravel 13). Tu trabajo es encontrar dónde **lo que se publica** y **lo que el código hace** dicen cosas distintas. En este proyecto la documentación ya contradijo al código varias veces (un DDL con índices inexistentes, `users.id` UUID contra bigint), así que ninguno de los dos se presume correcto.

# Objetivo

Para cada endpoint del contrato: qué dice la especificación y qué hace la implementación, en una tabla, con un riesgo por diferencia y un **characterization test** que fije el comportamiento actual.

**No cambies código, ni el readme, ni tests existentes.** Si escribís los characterization tests, van en archivos nuevos y solo si el usuario lo pide.

# Entrada

- **Contrato:** la sección `## 4. Especificación de la API` de `$ARGUMENTS` o, si viene vacío, de `/home/diegomotta/Documentos/Personal Develops/agendallenaia/readme.md` (el repo de entrega). Es un bloque `openapi: 3.0.3` dentro del markdown, con `servers: http://localhost:8000/api/v1`.
- **Implementación:** seguí la cadena real de cada endpoint, no la que el nombre sugiere:
  1. `routes/api.php` (prefijo `api/` + `v1/...`), con sus middlewares y `->name()`. Confirmá con:
     ```bash
     docker compose exec -T app php artisan route:list --path=api/v1 -v
     ```
  2. El controlador y método (`app/Http/Controllers/...`).
  3. La validación: `FormRequest`, `$request->validate()`, o chequeos a mano (`$request->query(...)`, `hash_equals`, firma HMAC).
  4. Todo `return`/`abort`/`response()` del método: **código HTTP**, content-type y cuerpo.
  5. Lo que pasa después (job encolado, redirect) si el contrato lo promete.

⚠️ Las rutas `dev/meta/...` y `dev/google/...` son los simuladores locales: **no** son parte del contrato público. Si aparecen expuestas fuera de `local`, eso sí es un hallazgo.

# Qué comparar, por endpoint

| Aspecto | Contrato (OpenAPI) | Código |
| :-- | :-- | :-- |
| Método y path | `paths.<path>.<method>` + `servers.url` | `route:list` |
| Parámetros | `in` (query/header/path), `required`, `schema.type`, `enum`, `format` | qué lee el controlador y qué hace si falta o no coincide |
| Cuerpo | `requestBody.required`, content-type, forma del ejemplo | cómo se decodifica; qué pasa con un cuerpo no decodificable |
| Respuestas | cada código y su `description`/content-type | cada salida real del método, **incluidas las que el contrato no menciona** (`422`, `500`, `419` de CSRF, `429`) |
| Seguridad | firma, `state` firmado, verify_token | middleware y comparación real (¿`hash_equals` o `===`?, ¿sobre el cuerpo crudo?) |
| Efectos | «encola el payload», «deduplica por id», «guarda encriptado» | el `dispatch`, la clave de idempotencia, el cast `encrypted` |

Casos que ya sabés que hay que mirar en este contrato:

- `GET /webhooks/whatsapp`: ¿`hub.mode` distinto de `subscribe` da 403 como dice el `enum`? ¿Qué código sale con parámetros incompletos?
- `POST /webhooks/whatsapp`: firma ausente/inválida → ¿`401` o `403`? ¿Se valida sobre el cuerpo crudo? ¿Un JSON roto da `200` como promete? ¿El tenant se resuelve por `metadata.phone_number_id`?
- `GET /oauth/google/callback`: el contrato declara `302` y tres destinos (`conectado`, `?google=cancelado`, `?google=error&motivo=state_invalido`). ¿Existen los tres? ¿El grupo con middleware `web` cambia algo (sesión, CSRF)?

# Pasos

1. Extraé del readme el bloque OpenAPI y listá cada operación (método + path).
2. Para cada una, recorré la cadena de implementación y llená la tabla.
3. Buscá **endpoints que existen y no están en el contrato** (en `api/v1`), y **operaciones del contrato que no tienen ruta**.
4. Mirá qué tests ya fijan cada comportamiento (`grep -rn "webhooks/whatsapp\|oauth/google" tests/ e2e/`).
5. Para cada diferencia, proponé un characterization test de integración (`tests/Feature/`, `RefreshDatabase`, `Http::fake()` en el borde) que capture **lo que hace hoy el código**, nombrado como tal, para que el cambio posterior —arreglar el código o arreglar el contrato— sea una decisión explícita y no un accidente.

Si corrés algún test, con base propia:

```bash
docker compose exec -T -e DB_DATABASE=agendallena_test_contrato app php artisan test --filter=NombreDelTest
```

# Salida

Una tabla por endpoint:

```markdown
### POST /api/v1/webhooks/whatsapp

| Campo / aspecto | Contrato | Implementación (archivo:línea) | ¿Coincide? | Riesgo |
| :-- | :-- | :-- | :-: | :-- |
```

Riesgo en una línea, en términos de quien consume la API: **Meta** (reintentos, suscripción del webhook), **Google** (consentimiento) o **quien lea el readme**.

Debajo:

1. Endpoints fuera del contrato y operaciones del contrato sin ruta.
2. Characterization tests propuestos: nombre, qué fija, archivo sugerido.
3. Para cada diferencia, ⚠️ **qué hay que decidir**: ¿se corrige el código o el contrato? No lo decidas vos.
