---
fecha: 2026-07-xx
objetivo: Diseñar el modelo de datos multi-tenant del MVP en MySQL 8 — diagrama entidad-relación en mermaid y documento técnico con principios y DDL de las siete tablas de dominio.
estado: reconstruido
origen: ingenieria inversa de los artefactos (.claude/docs/02-arquitectura/03-der-entidad-relacion.md y 04-modelo-de-datos.md en HEAD, version original), no es el prompt original
---

## Advertencia de trazabilidad

⚠️ **Este no es el prompt que se ejecutó.** El DER y el modelo de datos se generaron antes de que
existiera `/meta-prompt`, así que su prompt no se guardó. Se reconstruyó el 2026-11-02 leyendo
`git show HEAD:` de los dos archivos —la versión **anterior** a que ese mismo día se llevaran al
esquema real—.

⚠️ **La fecha es una estimación.** Un solo commit para toda la documentación. Lo seguro: posterior al
PRD (implementa RNF-01, RNF-02 y las tablas que nombra) y **anterior al 2026-08-05**, cuando el prompt
de story maps ya leía el DDL. Las migraciones que dicen *"DDL de referencia:
04-modelo-de-datos.md"* son del 2026-08-19.

⚠️ **Hasta la versión de HEAD tiene ediciones encima de la salida original**, y este prompt no las
genera:

| Edición posterior | Cómo se reconoce |
| :-- | :-- |
| `business_settings`, `messages`, `processed_messages` en el DDL | Su comentario cita T-014, T-048 y T-011 |
| `attendance*` y `live_event_id` en `bookings`, más sus índices | Citan T-036, T-030 y T-042 |
| El bullet "Optimización de Latencia" del §1 | Cita T-009 |
| `UNIQUE(booking_id, type)` en `notification_logs` | Lo agrega T-009 (lo dice su migración) |

Lo que queda al restarlas es la salida probable: **siete tablas** —`tenants`, `users`,
`integrations`, `quote_rules`, `conversations`, `bookings`, `notification_logs`— en el DER y en el DDL.

⚠️ **Hay una versión todavía anterior** en la raíz de `docs/`: `modelo de datos.md` (diccionario por
entidad en tablas `Atributo | Tipo | Restricciones | Descripción`, mapa de relaciones e "Índices
Críticos") y `diagrama entidad relacion  - DER.md` (tipos `uuid`/`bigint`, sin valores de enum,
`CONVERSATIONS o{--o{ BOOKINGS`). Parecen una primera iteración que se rehízo al pasar a la
convención `NN-kebab-case`; el encabezado del 04 todavía dice
`docs/02-arquitectura/01-modelo-de-datos-tecnico.md`, que es el nombre que el prompt le habrá pedido.
**Este prompt reconstruye la segunda**, la de `02-arquitectura/`. No se sabe si fueron dos prompts
o uno con una corrección.

**Qué es fiable y qué no:** la forma de los dos artefactos —tipos del DER, comentarios con los
valores de enum, encabezado de metadatos, `SET FOREIGN_KEY_CHECKS`, bloques de comentario por tabla,
nombres `fk_`/`idx_`— está verificada. El *fraseo* y el reparto entre lo que pidió el prompt y lo que
decidió el modelo (por ejemplo, UUID en `users`) son inferencia.

**Usarlo para regenerar produciría un modelo equivalente al de diseño, no al que existe.** El que
existe está en [`04-modelo-de-datos.md`](../../docs/02-arquitectura/04-modelo-de-datos.md), al día con
la base desde el 2026-11-02.

## Pedido original

No conservado. Del artefacto se deduce un pedido con esta forma:

> armá el DER en mermaid y el modelo de datos técnico con el DDL de MySQL, multi-tenant con
> tenant_id, para el PRD

## Prompt

```markdown
# Rol

Actuás como **arquitecto de datos senior** con experiencia en MySQL 8, Laravel Eloquent y SaaS
multi-tenant de base compartida. Diseñás pensando en el índice que va a usar cada consulta caliente
y en que una PyME nunca vea los datos de otra.

# Objetivo

Diseñar el **modelo de datos del MVP** y entregarlo en dos documentos: el **diagrama
entidad-relación** en mermaid y el **modelo de datos técnico** con principios y DDL ejecutable.

# Contexto / entrada

* **PRD:** `.claude/docs/01-producto/02-prd.md`. Lo que tiene que persistir sale de ahí:
  * RNF-01 — multi-tenancy por columna discriminadora `tenant_id` en todas las tablas e índices.
  * RNF-02 — todo instante en UTC; la zona del tenant se guarda y se usa solo para convertir.
  * RF-A3 — estado de la conversación por usuario final (`conversations`, con `current_state` y
    contexto).
  * RF-B1 — `access_token` y `refresh_token` de Google **cifrados en reposo**.
  * RF-B3 — el turno guarda el id del evento externo para la sincronización inversa.
  * RF-C1 — reglas de cotización con fórmula y esquema de parámetros.
  * RF-D1 — registro de cada recordatorio enviado (t-24 h, t-2 h, confirmación) con el estado que
    devuelve Meta.
  * RF-E1/E2 — usuarios del panel con rol, y lo que el panel lista.
* **C4:** `.claude/docs/02-arquitectura/01-c4-nivel-2-contenedores.md` y `02-c4-nivel-3-componentes.md`
  — MySQL es la base de verdad, Redis es colas y caché; el aislamiento lo aplican los repositorios
  Eloquent.
* **Motor:** MySQL 8.0, InnoDB, `utf8mb4_unicode_ci`.

# Instrucciones

1. **Tablas.** Una por entidad del dominio: `tenants`, `users`, `integrations` (proveedor como enum
   que incluya `meta_whatsapp`, `google_calendar`, `outlook_calendar`, `google_sheets`),
   `quote_rules`, `conversations`, `bookings`, `notification_logs`. Todas con `tenant_id` salvo
   `tenants`.
2. **Claves primarias, con criterio declarado:** UUID `CHAR(36)` en las entidades raíz de
   configuración y seguridad (`tenants`, `users`), para evitar la enumeración de recursos;
   `BIGINT UNSIGNED AUTO_INCREMENT` en las tablas de alto volumen, para índices B-Tree compactos.
3. **Claves foráneas con nombre** (`fk_<tabla>_<referida>`) y acción explícita: `CASCADE` hacia
   `tenants`; `SET NULL` donde la fila hija tiene que sobrevivir a la madre (el turno sobrevive a su
   conversación).
4. **Índices compuestos que lideran con `tenant_id`**, nombrados `idx_<tabla>_<columnas>`, para las
   consultas calientes: resolver la conversación por teléfono en cada webhook y la consulta del
   scheduler de recordatorios.
5. **Enums con sus valores** en el DDL, y los mismos valores como comentario en el DER.

## DER (`03-der-entidad-relacion.md`)

* `erDiagram` de mermaid. Primero las relaciones, cada una con **un verbo en español** como etiqueta
  (`"posee"`, `"configura"`, `"genera"`, `"audita"`); después las entidades en MAYÚSCULAS.
* Tipos sin espacios ni paréntesis, para que mermaid los acepte: `char36`, `bigint_unsigned`,
  `string`, `enum`, `json`, `decimal`, `datetime`, `timestamp`, `boolean`.
* Marcar `PK`, `FK` y `UK`. Los enums llevan sus valores como comentario:
  `enum status "'active' | 'suspended' | 'trial'"`.

## Modelo de datos (`04-modelo-de-datos.md`)

* Título `# Modelo de Datos Técnico (MySQL 8 / Laravel Eloquent)` y un encabezado de metadatos:
  **Documento**, **Motor de BD**, **Cache & Memory Store**, **Estrategia Multi-Tenant**.
* **§1 Visión General y Definición Estratégica:** un párrafo sobre el patrón (base compartida con
  discriminador) y una lista de **principios fundamentales** — aislamiento lógico con *Global Scopes*
  de Eloquent mediante un trait, estrategia de claves primarias con su porqué, y optimización de
  latencia con los índices compuestos.
* **§2 Sentencias DDL:** un solo bloque `sql` ejecutable de punta a punta, entre
  `SET FOREIGN_KEY_CHECKS = 0;` y `= 1;`, con `CREATE TABLE IF NOT EXISTS`, cada tabla precedida por
  un bloque de comentario `-- Table: <nombre>` y cerrada con
  `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`. `created_at`/`updated_at` con
  `DEFAULT CURRENT_TIMESTAMP` / `ON UPDATE CURRENT_TIMESTAMP`.
* Los tokens van en `TEXT` y se documentan como cifrados por la aplicación (Laravel Encrypter), no
  por la base.

# Restricciones

* No agregues tablas que el PRD no pida. Una tabla de más es una migración de más.
* No uses vistas, triggers ni procedimientos: la lógica vive en Laravel.
* El DER y el DDL tienen que coincidir columna por columna.
* El DER es **solo el diagrama**: sin texto alrededor ni bloque ```` ``` ````.
* Español rioplatense en la prosa; nombres de tablas y columnas en inglés, `snake_case`.

# Formato de salida

| Archivo | Contenido |
| :-- | :-- |
| `.claude/docs/02-arquitectura/03-der-entidad-relacion.md` | El `erDiagram` |
| `.claude/docs/02-arquitectura/04-modelo-de-datos.md` | Encabezado, §1 y §2 con el DDL |
```

## Supuestos de la reconstrucción

1. **La estrategia mixta de PK (UUID en raíces, BIGINT en volumen) fue pedido, no ocurrencia del
   modelo.** El documento la justifica con dos argumentos distintos, uno por tipo. Es el supuesto que
   más pesa: fue lo primero que el código no siguió (`users.id` quedó en BIGINT).
2. **Las siete tablas son la salida completa.** Las otras tres del DDL de HEAD citan tickets
   posteriores.
3. **Los índices del §1 original no se pueden recuperar.** El bullet de HEAD fue reescrito por T-009.
   La versión de la raíz de `docs/` lista tres (`(tenant_id, user_phone)` **único**,
   `(tenant_id, status, start_time)` y `(tenant_id, integration_id, start_time, end_time)`); ninguno
   de los tres quedó así en la base.
4. **`quote_rules` era parte del pedido** porque RF-C1 estaba en el alcance. Nunca se migró: el
   cotizador quedó diferido.

## Resultado

Ejecutado antes del 2026-08-05 (con el prompt original, no con este). Artefactos:
`.claude/docs/02-arquitectura/03-der-entidad-relacion.md` (siete entidades, nueve relaciones) y
`04-modelo-de-datos.md` (principios + DDL de siete tablas).

Fue la referencia de las migraciones del 2026-08-19, que se desviaron de él en lo que el código
necesitó (`access_token` nullable, `settings` como `TEXT` cifrado, `users.id` BIGINT, único
`(provider, account_identifier)`). El 2026-11-02 los dos documentos se reemplazaron por el esquema
real; esta es la única descripción que queda de cómo se pidió el original.
