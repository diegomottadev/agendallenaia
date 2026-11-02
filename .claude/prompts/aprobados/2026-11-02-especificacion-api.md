---
fecha: 2026-11-02
objetivo: Especificar en OpenAPI 3 los endpoints públicos del backend (webhook de Meta y OAuth de Google, RF-A1, RF-A2 y RF-B1) a partir del código que existe, para la sección 4 del readme de la entrega.
estado: reconstruido
origen: ingenieria inversa del codigo (routes/api.php, app/Http/Controllers/Api/*, VerifyMetaSignature) y del PRD (RF-A1, RF-A2, RF-B1); no hubo artefacto previo
---

## Advertencia de trazabilidad

⚠️ **No hubo artefacto previo.** A diferencia de los otros prompts reconstruidos, este no se
reconstruyó desde un documento de API existente, porque **nunca existió uno**: el repositorio no
tiene `docs/` de API ni archivo OpenAPI. **Este prompt se usó para la sección 4 del readme de la
entrega** (*Especificación de la API* en la plantilla de AI4Devs). No hay que leerlo como el origen
de un documento de API anterior: no lo hay.

⚠️ **Tampoco es el prompt original**, si lo hubo: no se guardó. Se reconstruyó el 2026-11-02 desde
las fuentes que cualquier prompt de especificación tenía que leer —RF-A1, RF-A2 y RF-B1 del PRD,
`routes/api.php` y `app/Http/Controllers/Api/*`—. La fecha es la de la reconstrucción; no se sabe
cuándo se usó el original.

**Qué es fiable y qué no:**

* **Fiable — verificado contra el código al 2026-11-02:** las rutas, los parámetros, los códigos de
  respuesta, la validación de firma y los redirects del OAuth que el prompt pide documentar.
* **Inferencia:** el fraseo; la elección de los endpoints como "los principales"; y el texto de la
  plantilla de AI4Devs, que se cita de memoria (`consigna.md` solo dice que el readme lleva una
  sección de API) — verificarlo contra el repo `AI4Devs-finalproject` antes de confiar en el límite
  de endpoints.
* ⚠️ `routes/api.php` **está cambiando mientras se escribe esto** (hay mocks de desarrollo nuevos sin
  commitear). El prompt pide excluirlos, pero si cambian las rutas públicas, la salida cambia.

## Pedido original

No conservado. Del contexto de la entrega se deduce un pedido con esta forma:

> especificá en OpenAPI los endpoints principales de la API para la sección 4 del readme, a partir
> de lo que está construido

## Prompt

```markdown
# Rol

Actuás como **ingeniero backend senior y redactor de contratos de API**, con experiencia en
OpenAPI 3, webhooks de terceros y flujos OAuth 2.0. Documentás lo que el código hace, no lo que el
diseño prometía.

# Objetivo

Escribir la **especificación OpenAPI 3.0** de los endpoints públicos del backend de AgendaLlena,
lista para pegar en la **sección 4 (Especificación de la API)** del `readme.md` de la entrega, con un
ejemplo de petición y respuesta por endpoint.

# Contexto / entrada

| Fuente | Qué aporta |
| :-- | :-- |
| `.claude/docs/01-producto/02-prd.md` | **RF-A1** (handshake), **RF-A2** (ingesta con firma HMAC, 200 en < 200 ms, encolado), **RF-B1** (OAuth con tokens cifrados en reposo). Es el *por qué* de cada endpoint |
| `routes/api.php` | Las rutas reales. Todo lo de este archivo vive bajo el prefijo `/api` de Laravel |
| `app/Http/Controllers/Api/WhatsAppWebhookController.php` | `verify` y `receive` |
| `app/Http/Middleware/VerifyMetaSignature.php` | Validación de `X-Hub-Signature-256` |
| `app/Http/Controllers/Api/GoogleOAuthController.php` | `redirect` y `callback` |

Los endpoints a documentar, agrupados por requisito:

| Requisito | Método y ruta | Lo que hay que reflejar |
| :-- | :-- | :-- |
| RF-A1 | `GET /api/v1/webhooks/whatsapp` | Query `hub.mode`, `hub.verify_token`, `hub.challenge`. `200` devuelve el challenge **en `text/plain`, sin comillas**. `403` si falta un parámetro, si `mode` no es `subscribe` o si el token no es de ningún tenant. El `verify_token` es **por tenant**, no compartido. Sin firma: Meta autentica el handshake con el token |
| RF-A2 | `POST /api/v1/webhooks/whatsapp` | Header `X-Hub-Signature-256: sha256=<hex>`, HMAC SHA256 del **cuerpo crudo** con el app secret. `401` sin cabecera, con formato inválido, con firma que no coincide, o si el servidor no tiene el secreto configurado (falla cerrado). `200` con cuerpo vacío con firma válida, **también si el cuerpo no es JSON**: devolver error haría que Meta reintente algo que no va a mejorar. El procesamiento es asíncrono: el `200` no significa que el mensaje se haya respondido |
| RF-B1 | `GET /api/v1/oauth/google/redirect` | Requiere sesión web (cookie) y rol `owner`/`admin`. `302` a la pantalla de consentimiento de Google con un `state` firmado que transporta el tenant. El tenant sale del usuario, nunca de un parámetro |
| RF-B1 | `GET /api/v1/oauth/google/callback` | **Sin** autenticación de sesión: la autenticación es el `state` firmado. Query `code`, `state`, `error`. Siempre `302` al panel: `/panel?google=conectado`, `?google=cancelado` (el usuario rechazó), o `?google=error&motivo=state_invalido|sin_codigo|canje_fallido`. Guarda `access_token`/`refresh_token` cifrados y conserva el `refresh_token` anterior si Google no manda uno nuevo |

# Instrucciones

1. **Leé el código antes que el PRD.** Donde difieran, manda el código y lo señalás con ⚠️ fuera del
   bloque YAML.
2. Un documento OpenAPI 3.0 en YAML: `info` (título, versión, descripción de una línea), `servers`
   con una URL de ejemplo, `tags` por requisito (`RF-A1`, `RF-A2`, `RF-B1`), `paths` y
   `components.securitySchemes`.
3. **Esquemas de seguridad honestos:** la firma HMAC de Meta como `apiKey` en header con la
   explicación en `description` (OpenAPI no modela HMAC), y la sesión del panel como `apiKey` en
   cookie. El handshake y el callback no llevan esquema: dejá dicho cómo se autentican.
4. **El payload del webhook es de Meta, no nuestro.** Modelá solo la envoltura que el sistema lee
   (`object`, `entry[].changes[].value` con `messages` o `statuses`) y marcá el resto como
   `additionalProperties: true`.
5. **Un ejemplo por operación** con `example` dentro del YAML: un handshake válido, un mensaje de
   texto entrante, un callback exitoso y uno cancelado.
6. Para cada respuesta, una `description` de una línea que diga **por qué** ese código y no otro
   cuando no es obvio (el `200` ante cuerpo no decodificable, el `302` sin `auth`).
7. Si la plantilla limita la cantidad de endpoints, contá RF-A1 y RF-A2 como uno (misma ruta, dos
   métodos) y RF-B1 como uno (el par `redirect` + `callback` es un solo flujo).

# Restricciones

* **No documentes lo que no existe.** El C4 dibuja un *REST API Gateway* con CRUD de configuración:
  no hay tal API, el panel es web con sesión (`routes/web.php`) y queda afuera. Tampoco existe ruta
  para las *Watch Notifications* de Google (RF-B3).
* **Excluí los mocks de desarrollo** (`/api/dev/meta/*`, `/api/dev/google/*`, detrás de
  `SoloConSimulador`): no son API pública.
* No inventes campos de respuesta: los dos webhooks responden con cuerpo vacío o texto plano, y el
  OAuth solo con redirects.
* Español rioplatense en `description` y en el texto que acompaña; claves de OpenAPI en inglés.

# Formato de salida

Para pegar en la sección 4 del `readme.md`:

1. Un párrafo de dos o tres oraciones: qué es público, por qué hay tan poco (el resto es panel web) y
   cómo se autentica cada grupo.
2. El bloque ```` ```yaml ```` con la especificación completa.
3. Debajo, solo si los hay, los ⚠️ donde el código y el PRD no coinciden.
```

## Supuestos de la reconstrucción

1. **El alcance son los cuatro endpoints de `routes/api.php` que no son de desarrollo.** El pedido
   nombraba RF-A1, RF-A2 y RF-B1, y son exactamente los que tienen ruta pública en ese archivo.
2. **El código manda sobre el PRD.** El PRD no dice que el handshake responda `text/plain`, que el
   webhook devuelva `401` y no `403`, ni que el callback no lleve sesión; el código sí, y la sección 4
   tiene que describir lo que el evaluador va a poder probar.
3. **El límite de endpoints de la plantilla** se cita de memoria y por eso la instrucción 7 es
   condicional.

## Resultado

Usado para la sección 4 del readme de la entrega. **No dejó artefacto en `.claude/docs/`**: la salida
vive en el readme, no en la documentación del repositorio.
