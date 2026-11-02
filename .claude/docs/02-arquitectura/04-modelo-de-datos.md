# Modelo de Datos Técnico (MySQL 8.4 / Laravel Eloquent)

**Documento:** `docs/02-arquitectura/04-modelo-de-datos.md`  
**Motor de BD:** MySQL 8.4 (InnoDB, `utf8mb4_unicode_ci`) — la base de desarrollo corre `8.4.13`  
**Cache & Memory Store:** Redis 7.x  
**Estrategia Multi-Tenant:** Base de datos compartida con discriminador lógico (`tenant_id`) e indexación compuesta que lidera con `tenant_id`.

> **Actualizado al esquema real el 2026-11-02.** El DDL de la sección 2 es lo que devuelve `SHOW CREATE TABLE` sobre la base que corre, con las 26 migraciones de `database/migrations/` aplicadas. Se limpió solo el ruido: `AUTO_INCREMENT=n` y el `COLLATE` repetido en cada columna. El diagrama está en [`03-der-entidad-relacion.md`](03-der-entidad-relacion.md).

### Qué cambió respecto de la versión anterior

La versión anterior era el DDL de diseño con anotaciones agregadas a mano a medida que salían los tickets. Había empezado a divergir de la base en lo que no se anotó.

| Cambio | Tabla | Por qué |
| :-- | :-- | :-- |
| **Sale** `quote_rules` | — | No existe en la base. El cotizador quedó diferido (T-016, T-040, T-041) y la tabla nunca se migró |
| **Entran** `lead_spreadsheets` y `reconciliation_findings` | nuevas | T-044 y § 8/§ 9 de las decisiones del 2026-08-21. No estaban documentadas |
| `messages`, `processed_messages` y `business_settings` | ya estaban | Se alinean al real: `business_settings` suma `search_window_days`, `no_availability_message` y `human_phone` |
| `users.id` pasa a `BIGINT` y `tenant_id` a nullable | `users` | Es lo que existe. El doc decía `CHAR(36)` UUID; la migración quedó con el default de Laravel (T-046) |
| Único de `email` pasa a `(tenant_id, email)` | `users` | H-14: una persona puede tener cuenta en dos PyMEs |
| `access_token` nullable, `settings` pasa de `JSON` a `TEXT` | `integrations` | Meta existe sin token; `settings` se guarda **cifrado** y un texto cifrado no es JSON válido |
| Único `(provider, account_identifier)` e índice `(tenant_id, provider)` | `integrations` | Sin el único no hay cómo resolver el `phone_number_id` de Meta a un tenant (T-010) |
| `state_version`, `lead_sheet_id`, `lead_sheet_row`, `handoff_*` + índice de pendientes | `conversations` | T-019, T-045, T-035 |
| `live_event_id` generada, `attendance*` | `bookings` | T-030/T-042 y T-036. Ya estaban anotadas; se pasa al DDL real |
| `failure_reason`, `failed_at` | `notification_logs` | T-039 |
| Timestamps sin `DEFAULT CURRENT_TIMESTAMP` | todas | Las crea `$table->timestamps()`: `NULL DEFAULT NULL`, las llena Eloquent. El DDL viejo prometía defaults que la base no tiene |
| Nombres de índices y FKs | todas | Los reales son los que genera Laravel (`<tabla>_<cols>_unique`), no los `idx_*` de diseño |
| Motor `MySQL 8.0` → `8.4` | — | Es lo que corre, y lo que da por supuesto la migración de `business_settings` |

---

## 1. Visión General y Definición Estratégica

El modelo de datos está diseñado bajo el patrón **Multi-Tenant de Base de Datos Compartida (Discriminador Clave)**. Todas las tablas de negocio contienen una columna `tenant_id` con clave foránea a `tenants` (`ON DELETE CASCADE`): borrar un tenant borra todo lo suyo.

### Catálogo de tablas

| Tabla | Qué guarda | Ticket que la crea | Filas por tenant |
| :-- | :-- | :-- | :-- |
| `tenants` | La PyME | T-009 | — |
| `users` | Personal que entra al panel | Laravel + T-046 | pocas |
| `integrations` | Credenciales de Meta y Google, cifradas | T-008/T-012 | 1 por proveedor |
| `business_settings` | Horarios, duración de turno, buffers y textos del bot | T-014 | **exactamente 1** |
| `lead_spreadsheets` | La planilla de Google Sheets donde caen los leads | T-044 | **0 o 1** |
| `conversations` | **Estado** de la conversación por teléfono | T-009/T-018 | una por cliente final |
| `messages` | **Historial** de la conversación | T-048 | la que más crece |
| `processed_messages` | Candado de idempotencia de la ingesta | T-011 | una por mensaje entrante |
| `bookings` | Turnos, con su evento de Google | T-009 | una por turno |
| `notification_logs` | Envíos de WhatsApp por turno (confirmación y recordatorios) | T-009/T-039 | hasta 3 por turno |
| `reconciliation_findings` | Turnos "en duda" que detecta la conciliación | § 8/§ 9 | pocas |

Además existen las tablas de infraestructura de Laravel —`jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`, `sessions`, `password_reset_tokens`, `personal_access_tokens` y `migrations`—. No llevan `tenant_id`, no son modelo de dominio y no se documentan acá.

### Principios Fundamentales

* **Aislamiento lógico (RNF-01):** a nivel ORM, con el Global Scope del trait `App\Models\Concerns\BelongsToTenant`, que fuerza `WHERE tenant_id = ?`. Lo usan `Conversation`, `Message`, `Booking`, `NotificationLog`, `BusinessSetting`, `LeadSpreadsheet` y `ReconciliationFinding`.
  * ⚠️ **`Integration` y `User` no lo usan, a propósito:** la ingesta resuelve el tenant *a partir* de la integración (el `phone_number_id` de Meta), o sea que consulta antes de saber de quién es. Ahí el filtro va explícito. `processed_messages` no tiene modelo: se escribe con el query builder y el filtro también va a mano.
  * El Global Scope **no alcanza** a `DB::table()`, `whereRaw()`, joins manuales ni subconsultas.
* **Todo índice compuesto lidera con `tenant_id`.** Es la regla del proyecto y la cumplen todos los índices de lectura. Hay **dos excepciones deliberadas**, y las dos son únicos que existen para impedir algo *entre* tenants:
  * `integrations (provider, account_identifier)` — un mismo número de WhatsApp no puede pertenecer a dos PyMEs, y la ingesta busca por número sin saber el tenant.
  * `lead_spreadsheets (spreadsheet_id)` — dos negocios no pueden compartir planilla (decisión § 11). Un `(tenant_id, spreadsheet_id)` permitiría justo eso.
  * ⚠️ Y una que **no** es deliberada: `notification_logs (booking_id, type)`. Ver la tabla.
* **Los candados de concurrencia viven en el esquema, no en PHP.** Es la lección que se repite en `processed_messages`, `bookings.live_event_id`, `lead_spreadsheets` y `reconciliation_findings`: un `SELECT` previo deja abierta la ventana hasta el `INSERT`, y dos workers en paralelo la pasan los dos. El único índice no.
* **Estrategia de claves primarias:**
  * **UUID (`CHAR(36)`)** solo en `tenants`, para que el id del negocio no sea enumerable.
  * **`BIGINT UNSIGNED` autoincremental** en el resto, **incluida `users`**. ⚠️ El diseño original quería UUID en `users`; no se hizo porque `personal_access_tokens.tokenable_id`, `bookings.attendance_marked_by` y `reconciliation_findings.resolved_by` ya apuntan a esa PK. Si se unifica, es antes de tener usuarios reales (T-046).
* **Fechas en UTC (RNF-02).** Todo `timestamp`/`datetime` se guarda en UTC y se convierte a `tenants.timezone` en el borde. La única excepción es `business_settings.business_hours`, que son reglas ("abrimos a las 9"), no instantes, y van en hora local.
* **Secretos cifrados en reposo (RF-B1).** `integrations.access_token`, `refresh_token` y `settings` llevan el cast `encrypted` / `encrypted:array` del modelo `Integration`. La base solo ve texto cifrado con la `APP_KEY`: **no se puede filtrar por SQL** sobre esas columnas (el handshake de Meta compara el `verify_token` en memoria por eso), y rotar la `APP_KEY` sin re-cifrar las deja ilegibles.
* **`VARCHAR` y no `ENUM` cuando el catálogo lo define un tercero o va a crecer:** `messages.type` (lo define Meta), `conversations.handoff_reason` (falta el disparador de `out_of_range`), `reconciliation_findings.type`. Un `ENUM` obliga a migrar una tabla con datos por cada valor nuevo.

---

## 2. Sentencias DDL (MySQL 8.4, según `SHOW CREATE TABLE`)

```sql
-- -----------------------------------------------------
-- Table: tenants
--
-- Raíz del modelo. `timezone` es la única fuente de la zona del negocio: la
-- base guarda UTC y esta columna se usa solo para convertir en el borde (RNF-02).
-- -----------------------------------------------------
CREATE TABLE `tenants` (
  `id` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `status` enum('active','suspended','trial') NOT NULL DEFAULT 'trial',
  `timezone` varchar(50) NOT NULL DEFAULT 'America/Argentina/Buenos_Aires',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: users   (Laravel + T-046)
--
-- Era la tabla de Laravel sin tocar; T-046 le agregó `tenant_id` y `role`.
--
-- ⚠️ `id` BIGINT, no UUID — ver principios.
-- ⚠️ `tenant_id` NULLABLE: la tabla podía tener filas sin tenant al que
-- asignarlas. La relación se declara igual. Un usuario sin tenant no pasa la
-- autorización del panel, pero la base no lo impide.
--
-- `role` default `staff`, el más restrictivo: si un alta se olvida de fijarlo,
-- el error es "no puede configurar" —visible— y no "puede todo".
--
-- Único `(tenant_id, email)` y no `email` solo (H-14): con el global, el gestor
-- que administra dos negocios no puede tener cuenta en los dos.
-- -----------------------------------------------------
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` char(36) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('owner','admin','staff') NOT NULL DEFAULT 'staff',
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_tenant_id_email_unique` (`tenant_id`,`email`),
  KEY `users_tenant_id_role_index` (`tenant_id`,`role`),
  CONSTRAINT `users_tenant_id_foreign` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: integrations   (T-008, T-010, T-012)
--
-- Una fila por cuenta conectada. Para `meta_whatsapp`, `account_identifier` es
-- el `phone_number_id`; para Google, el correo de la cuenta.
--
-- CIFRADO: `access_token`, `refresh_token` (cast `encrypted`) y `settings`
-- (cast `encrypted:array`) se cifran en el modelo `Integration` con la
-- APP_KEY. Por eso `settings` es TEXT y no JSON: lo que se guarda es el texto
-- cifrado de un JSON, que no es JSON válido. Ahí vive el `verify_token` de Meta.
--
-- `access_token` nullable: la integración de Meta existe desde que se configura
-- el webhook (T-008) y puede no tener token todavía. NOT NULL invita a guardar
-- cadenas vacías, que es peor que un NULL explícito.
--
-- `outlook_calendar` sigue en el ENUM sin código detrás (T-007: Outlook queda
-- fuera del MVP). Se conserva para no migrar la tabla si algún día entra.
-- -----------------------------------------------------
CREATE TABLE `integrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` char(36) NOT NULL,
  `provider` enum('meta_whatsapp','google_calendar','outlook_calendar','google_sheets') NOT NULL,
  `account_identifier` varchar(255) NOT NULL,
  `access_token` text,
  `refresh_token` text,
  `expires_at` timestamp NULL DEFAULT NULL,
  `settings` text,
  `status` enum('connected','disconnected','expired') NOT NULL DEFAULT 'connected',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  -- T-010 · Resuelve el `phone_number_id` que manda Meta a UN tenant. No lidera
  -- con `tenant_id` a propósito: la ingesta busca por número antes de saber de
  -- quién es, y un mismo número no puede ser de dos PyMEs.
  UNIQUE KEY `integrations_provider_account_identifier_unique` (`provider`,`account_identifier`),
  KEY `integrations_tenant_id_provider_index` (`tenant_id`,`provider`),
  CONSTRAINT `integrations_tenant_id_foreign` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: business_settings   (T-014, T-017, T-024)
--
-- Configuración operativa del negocio, EXACTAMENTE una fila por tenant: el
-- único sobre `tenant_id` garantiza "como mucho una" y `Tenant::booted()` crea
-- la fila con los valores por defecto al dar de alta el tenant, así que en la
-- práctica es 1:1.
--
-- `business_hours` va en JSON y no en columnas por día (decisión de T-014): la
-- jornada partida es la norma en las PyMEs latinoamericanas (9 a 13 y 16 a 20)
-- y un par apertura/cierre no la puede expresar. Forma:
--   {"mon": [["09:00","13:00"], ["16:00","20:00"]], "sun": []}
-- Lista vacía = cerrado. Las horas son LOCALES al tenant: son reglas, no
-- instantes. Se resigna consultarlo desde SQL; MySQL 8.4 puede indexar rutas
-- JSON con columnas generadas si un reporte lo pide en v2.
--
-- `search_window_days` (T-024) default 7: con 30 días una agenda ocupada casi
-- nunca da vacío y el mensaje de "agenda llena" no se dispararía nunca. El
-- spike T-005 midió que ampliar la ventana no cuesta latencia: es decisión de
-- producto, no técnica.
--
-- `no_availability_message` (T-017) es distinto del fallback: uno dice "no
-- tengo lugar", el otro "algo se rompió". Nullable en el esquema, pero la
-- migración rellenó las filas existentes con el texto por defecto.
--
-- `human_phone`: un número registrado en la Cloud API no puede usarse además
-- como chat humano (RF-A4 reescrito el 2026-08-20). Al derivar, el bot le dice
-- al cliente a qué otro número escribir. Nullable: un tenant sin segundo número
-- es un caso real y el mensaje de derivación tiene que seguir siendo honesto.
-- -----------------------------------------------------
CREATE TABLE `business_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` char(36) NOT NULL,
  `business_hours` json NOT NULL,
  `slot_duration_minutes` smallint unsigned NOT NULL DEFAULT '30',
  `buffer_minutes` smallint unsigned NOT NULL DEFAULT '10',
  `search_window_days` smallint unsigned NOT NULL DEFAULT '7',
  `welcome_message` text NOT NULL,
  `fallback_message` text NOT NULL,
  `no_availability_message` text,
  `human_phone` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `business_settings_tenant_id_unique` (`tenant_id`),
  CONSTRAINT `business_settings_tenant_id_foreign` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: lead_spreadsheets   (T-044)
--
-- La planilla de Google Sheets que la dueña vinculó para recibir leads. 0 o 1
-- por tenant: sin planilla, el volcado no corre.
--
-- Tabla propia y no una fila más de `integrations`: la planilla no es una
-- cuenta conectada, es una elección sobre la cuenta de Google que ya está.
-- Meterla ahí obligaría a inventarle un `account_identifier`.
--
-- Los dos únicos SON el candado:
-- - `tenant_id` único: un negocio, una planilla. Con dos filas el volcado
--   elegiría a cuál escribir por orden de SELECT (AC-26.4).
-- - `spreadsheet_id` único SOLO, sin `tenant_id` adelante (decisión § 11): dos
--   negocios no pueden compartir planilla. Un `(tenant_id, spreadsheet_id)`
--   permitiría exactamente la fuga que RNF-01 existe para impedir.
--
-- VARCHAR(191) y no 255: con utf8mb4 una columna indexada de 255 se acerca al
-- techo de 3072 bytes por índice sin necesidad. Un `spreadsheetId` son 44
-- caracteres. `sheet_name` es la hoja elegida: escribir en la primera que haya
-- le pisa a la dueña la que ya estaba usando.
-- -----------------------------------------------------
CREATE TABLE `lead_spreadsheets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` char(36) NOT NULL,
  `spreadsheet_id` varchar(191) NOT NULL,
  `sheet_name` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lead_spreadsheets_tenant_id_unique` (`tenant_id`),
  UNIQUE KEY `lead_spreadsheets_spreadsheet_id_unique` (`spreadsheet_id`),
  CONSTRAINT `lead_spreadsheets_tenant_id_foreign` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: conversations   (T-009, T-018, T-019, T-035, T-045)
--
-- ESTADO de la conversación por (tenant, teléfono). Fuente de verdad de la
-- máquina de estados; Redis `session:` es solo caché y lock (T-007).
--
-- `state_version` (T-019, AC-03.2): contador que sube en cada transición y
-- sella los botones. Sellar solo el estado emisor no alcanza: si la
-- conversación expira y vuelve a `SELECTING_SLOT` con otros horarios, un botón
-- de la lista vieja coincide en estado y reservaría un horario que ya no se
-- ofrece. Con el contador, ningún botón emitido antes pasa por actual.
--
-- `lead_sheet_id` + `lead_sheet_row` (T-045, AC-13.2): dónde quedó escrito el
-- lead, para que el volcado haga upsert y no agregue una fila nueva. No van en
-- `context_data` porque `PasoEntregado` lo restituye entero cuando un paso no
-- se entrega y la máquina lo limpia al reiniciar con "menu": la referencia se
-- perdería. Lleva también la planilla porque si la dueña cambia de planilla
-- (AC-26.4), "la fila 2" de la nueva es OTRA persona. No se llama
-- `spreadsheet_id` para no parecer una vinculación como la de `lead_spreadsheets`.
--
-- `handoff_*` (T-035, AC-20.1): tres columnas y no un booleano porque el
-- motivo es el dato que US-20 vino a buscar (`unknown_input` → problema de
-- flujo; `no_availability` → problema de capacidad, oportunidad comercial).
-- Al resolver se escribe `handoff_resolved_at` en vez de borrar la marca, para
-- no perder el histórico por causa. `handoff_reason` es VARCHAR: el tercer
-- motivo, `out_of_range`, no tiene disparador mientras el cotizador esté
-- diferido. ⚠️ Sin definir qué pasa con una derivación que nadie atiende.
-- -----------------------------------------------------
CREATE TABLE `conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` char(36) NOT NULL,
  `user_phone` varchar(50) NOT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `current_state` varchar(100) NOT NULL DEFAULT 'IDLE',
  `state_version` int unsigned NOT NULL DEFAULT '0',
  `context_data` json DEFAULT NULL,
  `lead_sheet_id` varchar(191) DEFAULT NULL,
  `lead_sheet_row` int unsigned DEFAULT NULL,
  `handoff_reason` varchar(50) DEFAULT NULL,
  `handoff_at` timestamp NULL DEFAULT NULL,
  `handoff_resolved_at` timestamp NULL DEFAULT NULL,
  `last_interaction_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  -- T-009 · Resolución de la conversación en cada webhook, camino crítico de
  -- RNF-04. ⚠️ NO es único (el diseño original lo pedía UNIQUE). Ver pendientes.
  KEY `conversations_tenant_id_user_phone_index` (`tenant_id`,`user_phone`),
  -- T-035 · Los pendientes de derivación del tenant, por antigüedad (AC-20.2):
  -- tenant, después el filtro `handoff_resolved_at IS NULL`, y cierra con la
  -- columna del ORDER BY para que el orden salga del índice.
  KEY `conversations_handoff_pendientes_index` (`tenant_id`,`handoff_resolved_at`,`handoff_at`),
  CONSTRAINT `conversations_tenant_id_foreign` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: messages   (T-048, adelantada del sprint 8)
--
-- HISTORIAL de la conversación. AC-14.4 promete "el historial completo" y
-- `conversations` guarda el estado, no la conversación. También la necesita
-- AC-08.1: un mensaje recibido durante una pausa tiene que quedar registrado.
--
-- RETENCIÓN: 12 meses. Es la tabla de mayor crecimiento y la única que guarda
-- contenido de terceros —el cliente final de la PyME no es usuario nuestro—.
-- ⚠️ La política está DEFINIDA, no APLICADA: no hay tarea de purgado.
--
-- `type` VARCHAR y no ENUM: los tipos los define Meta y agrega nuevos.
-- `whatsapp_message_id` es el `wamid`; NULL en un saliente hasta que Meta lo
-- confirma.
-- -----------------------------------------------------
CREATE TABLE `messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` char(36) NOT NULL,
  `conversation_id` bigint unsigned NOT NULL,
  `direction` enum('inbound','outbound') NOT NULL,
  `type` varchar(50) NOT NULL,
  `content` text,
  `payload` json DEFAULT NULL,
  `whatsapp_message_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  -- Idempotencia del historial: un reintento del job no duplica la fila. En
  -- MySQL un único admite varios NULL, así que no estorba a los salientes que
  -- todavía no tienen `wamid`.
  UNIQUE KEY `messages_tenant_id_whatsapp_message_id_unique` (`tenant_id`,`whatsapp_message_id`),
  -- Lo crea MySQL solo, para la FK.
  KEY `messages_conversation_id_foreign` (`conversation_id`),
  -- Historial ordenado de una conversación. ⚠️ Medido con 6.000 filas y UN
  -- tenant, el optimizador prefiere el de la FK y hace filesort sobre ~30
  -- filas: con un solo tenant la columna líder no discrimina. Forzado, el plan
  -- es `Backward index scan` sin filesort. Volver a medir con varios tenants
  -- antes de agregar un `(conversation_id, created_at)`: en la tabla que más
  -- crece, un índice de más se paga en cada insert.
  KEY `messages_tenant_id_conversation_id_created_at_index` (`tenant_id`,`conversation_id`,`created_at`),
  CONSTRAINT `messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`)
    REFERENCES `conversations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `messages_tenant_id_foreign` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: processed_messages   (T-011)
--
-- Candado de idempotencia de la ingesta. Meta reentrega todo webhook que no
-- recibió 200 a tiempo; sin esto el bot le responde dos veces al mismo mensaje.
-- La deduplicación no depende de un SELECT previo sino de que la base rechace
-- el segundo INSERT. (El lock de Redis `dedup:tenant:{id}:msg:{wamid}` cubre la
-- otra mitad: dos reentregas simultáneas que responderían antes de insertar.)
--
-- Va en MySQL y no en Redis a propósito: Redis es caché en este proyecto, y un
-- FLUSHALL o un desalojo por memoria reabriría la ventana de duplicados.
--
-- El `wamid` es único global, pero el único lidera con `tenant_id` por la
-- regla del proyecto. Sin `created_at`/`updated_at`: `processed_at` alcanza.
--
-- ⚠️ Sin política de purga: crece sin techo. Antes de escalar hay que borrar lo
-- anterior a la ventana de reintentos de Meta.
-- -----------------------------------------------------
CREATE TABLE `processed_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` char(36) NOT NULL,
  `message_id` varchar(255) NOT NULL,
  `processed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `processed_messages_tenant_id_message_id_unique` (`tenant_id`,`message_id`),
  CONSTRAINT `processed_messages_tenant_id_foreign` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: bookings   (T-009, T-030, T-036, T-042)
--
-- Turnos. `external_event_id` es el id del evento en Google Calendar; con la
-- clave de idempotencia determinística de `ClaveDeIdempotencia`, mismo tenant +
-- conversación + instante dan el mismo id, y así un reintento converge al
-- turno que ya existe en vez de duplicarlo. `start_time`/`end_time` en UTC.
--
-- `conversation_id` nullable con ON DELETE SET NULL: un turno sobrevive a que
-- se borre la conversación que lo originó.
--
-- T-036 · `attendance` en COLUMNA PROPIA, no como valor de `status`.
-- `status` responde "qué pasó con la reserva"; `attendance`, "vino o no". Son
-- ortogonales: un turno puede estar `confirmed` y `no_show` a la vez, y ese es
-- el caso que sostiene AC-17.4. Si `no_show` fuera un valor de `status`,
-- marcar la ausencia pisaría el dato de que había confirmado, y sin ese cruce
-- se ve que el ausentismo bajó pero no se le puede atribuir causa.
-- `attendance_marked_by`/`_at` auditan quién marcó y cuándo (US-17). Son BIGINT
-- porque `users.id` lo es; ON DELETE SET NULL: borrar al empleado no borra la
-- marca.
--
-- T-030 + T-042 · `live_event_id` es una columna GENERADA (VIRTUAL): vale
-- `external_event_id` mientras el turno está vivo y NULL cuando no. Existe para
-- que el único `(tenant_id, live_event_id)` se aplique SOLO entre turnos vivos.
--   - Con el único sobre `(tenant_id, external_event_id)` a secas (2026-08-20),
--     la fila `cancelled` seguía ocupando el evento: el cliente que cancelaba y
--     volvía a pedir el mismo horario generaba la misma clave, el INSERT chocaba
--     contra la fila muerta y `Reserva::turnoYaPersistido()` respondía "quedó
--     reservado" sin turno vivo.
--   - T-042 extendió "no vivo" a `rescheduled`: al mover un turno, su evento se
--     borra de Google, y la fila vieja seguía reclamando un evento inexistente
--     cuando el cliente volvía al horario original.
-- En MySQL dos NULL no chocan en un único, así que la fila muerta suelta el
-- candado sola, sin que ningún camino de cancelación tenga que acordarse de
-- limpiarla. Y CONSERVA su `external_event_id`: que un cliente canceló o movió
-- y volvió al mismo horario es el dato que el piloto va a querer mirar.
-- (Las `_utf8mb4'...'` de la expresión son como MySQL reescribe los literales.)
-- -----------------------------------------------------
CREATE TABLE `bookings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` char(36) NOT NULL,
  `conversation_id` bigint unsigned DEFAULT NULL,
  `integration_id` bigint unsigned NOT NULL,
  `external_event_id` varchar(255) NOT NULL,
  `live_event_id` varchar(255) GENERATED ALWAYS AS ((case when (`status` in (_utf8mb4'cancelled',_utf8mb4'rescheduled')) then NULL else `external_event_id` end)) VIRTUAL,
  `client_name` varchar(255) NOT NULL,
  `client_phone` varchar(50) NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `quote_amount` decimal(10,2) DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `status` enum('scheduled','confirmed','rescheduled','cancelled') NOT NULL DEFAULT 'scheduled',
  `attendance` enum('pending','attended','no_show') NOT NULL DEFAULT 'pending',
  `attendance_marked_by` bigint unsigned DEFAULT NULL,
  `attendance_marked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  -- T-030 · Un evento de Google no puede ser reclamado por dos turnos VIVOS del
  -- mismo tenant. En el esquema y no en un `if`: dos workers que reintentan el
  -- mismo agendamiento reciben los dos el 409 de Google y siguen los dos hacia
  -- el INSERT. No es único por `external_event_id` solo: dos cuentas de Google
  -- distintas pueden repetir un id.
  UNIQUE KEY `bookings_tenant_id_live_event_id_unique` (`tenant_id`,`live_event_id`),
  KEY `bookings_conversation_id_foreign` (`conversation_id`),
  KEY `bookings_integration_id_foreign` (`integration_id`),
  -- T-009 · Consulta del scheduler de recordatorios cada 10 minutos (RF-D1).
  KEY `bookings_tenant_id_start_time_status_index` (`tenant_id`,`start_time`,`status`),
  KEY `bookings_attendance_marked_by_foreign` (`attendance_marked_by`),
  -- Índice de LECTURA, aparte del único: resuelve la búsqueda por evento de la
  -- conciliación (T-030) y de la notificación entrante de Google (T-027). Al
  -- pasar el único a `live_event_id` dejó de cubrir ese plan, así que se
  -- declara explícito.
  KEY `bookings_tenant_id_external_event_id_index` (`tenant_id`,`external_event_id`),
  CONSTRAINT `bookings_attendance_marked_by_foreign` FOREIGN KEY (`attendance_marked_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `bookings_conversation_id_foreign` FOREIGN KEY (`conversation_id`)
    REFERENCES `conversations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `bookings_integration_id_foreign` FOREIGN KEY (`integration_id`)
    REFERENCES `integrations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `bookings_tenant_id_foreign` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: notification_logs   (T-009, T-039)
--
-- Un registro por envío de WhatsApp ligado a un turno: confirmación y
-- recordatorios t-24 h / t-2 h. El ENUM de `status` ya traía `delivered` y
-- `read`, así que los webhooks de estado de Meta (T-039) no necesitaron migrar.
--
-- `UNIQUE(booking_id, type)` (T-009, AC-10.4): un recordatorio de cada tipo por
-- turno, PARA SIEMPRE. Es el candado contra el recordatorio duplicado cuando el
-- scheduler corre dos veces sobre la misma ventana: el segundo INSERT falla y
-- el envío no sale. Consecuencia: el "reintentar" de US-24 actualiza la fila
-- existente, no inserta otra. ⚠️ Si alguna vez se habilitan varios intentos con
-- historia propia, el único tiene que sumar un número de intento.
--
-- T-039 · `failure_reason` + `failed_at`. Con `status = 'failed'` solo, el
-- dueño no distingue un número mal cargado —que arregla él— de un rechazo de
-- Meta —que no—. VARCHAR(500) y no TEXT: guarda `title`/`message` de Meta o una
-- frase nuestra y se muestra en una lista. `failed_at` no reusa `sent_at`:
-- `sent_at` NULL es lo que distingue el envío que nunca salió, y la lista de
-- fallidos se ordena por cuándo falló.
-- -----------------------------------------------------
CREATE TABLE `notification_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` char(36) NOT NULL,
  `booking_id` bigint unsigned NOT NULL,
  `type` enum('reminder_24h','reminder_2h','confirmation') NOT NULL,
  `whatsapp_message_id` varchar(255) DEFAULT NULL,
  `status` enum('queued','sent','delivered','read','failed') NOT NULL DEFAULT 'queued',
  `failure_reason` varchar(500) DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  -- ⚠️ Único compuesto que NO lidera con `tenant_id`. Es correcto como candado
  -- (`booking_id` ya pertenece a un solo tenant), pero rompe la regla del
  -- proyecto y deja sin índice compuesto las consultas por tenant —la lista de
  -- recordatorios fallidos de US-24 filtra por tenant y estado—. Ver pendientes.
  UNIQUE KEY `notification_logs_booking_id_type_unique` (`booking_id`,`type`),
  KEY `notification_logs_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `notification_logs_booking_id_foreign` FOREIGN KEY (`booking_id`)
    REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `notification_logs_tenant_id_foreign` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: reconciliation_findings   (decisiones § 8 y § 9 del 2026-08-21)
--
-- Turnos "en duda": desalineados que la conciliación detecta y UNA PERSONA
-- resuelve. Antes, un turno vivo cuyo evento el dueño había borrado de su
-- calendario se cancelaba en silencio: el cliente tenía la confirmación en el
-- celular y llegaba a la puerta. Avisarle automáticamente choca con la ventana
-- de 24 h de Meta (haría falta una plantilla aprobada que no existe).
--
-- Tabla propia y no un valor más de `bookings.status`: el mismo error que T-036
-- corrigió con la asistencia. Un turno puede estar `confirmed` Y en duda.
--
-- `type` VARCHAR(64): hoy el único tipo es `booking_without_event`; el
-- catálogo va a crecer (huérfano, evento movido de hora) y lo fija una
-- constante del modelo. 64 porque entra en el único.
-- `resolved_at`/`resolved_by`/`resolution` (`kept` | `cancelled`) NULL los
-- tres = pendiente, lo único que lista el panel. Misma auditoría que
-- `attendance_marked_by`, BIGINT por el mismo motivo. ⚠️ `kept` NO recrea el
-- evento en Google: la inconsistencia sigue, resuelta por decisión.
--
-- ⚠️ El único `(tenant_id, type, booking_id)` RETIENE también después de
-- resuelto, y es lo contrario de `live_event_id`. Allá la fila muerta tiene que
-- soltar el candado (el horario se liberó). Acá la fila resuelta tiene que
-- retenerlo: después de "mantener", el turno sigue vivo y sin evento, la
-- corrida de dentro de 15 minutos lo vuelve a ver, y si el candado se soltara
-- el dueño resolvería lo mismo cada 15 minutos. El hallazgo se cierra porque
-- una persona decidió, no porque el mundo cambió. Precio conocido: si el dueño
-- recrea el evento con el mismo id y lo vuelve a borrar, ese segundo borrado
-- no genera hallazgo.
-- -----------------------------------------------------
CREATE TABLE `reconciliation_findings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` char(36) NOT NULL,
  `booking_id` bigint unsigned NOT NULL,
  `type` varchar(64) NOT NULL,
  `detected_at` timestamp NOT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` bigint unsigned DEFAULT NULL,
  `resolution` varchar(32) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reconciliation_findings_tenant_id_type_booking_id_unique` (`tenant_id`,`type`,`booking_id`),
  KEY `reconciliation_findings_booking_id_foreign` (`booking_id`),
  KEY `reconciliation_findings_resolved_by_foreign` (`resolved_by`),
  CONSTRAINT `reconciliation_findings_booking_id_foreign` FOREIGN KEY (`booking_id`)
    REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `reconciliation_findings_resolved_by_foreign` FOREIGN KEY (`resolved_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `reconciliation_findings_tenant_id_foreign` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Pendientes del modelo

| ⚠️ Pendiente | Tabla | Qué falta |
| :-- | :-- | :-- |
| Purgado a 12 meses | `messages` | La retención está definida (T-048) y no hay tarea que la aplique |
| Purgado de la ventana de reintentos | `processed_messages` | Crece sin techo (T-011) |
| `users.id` UUID o BIGINT | `users` | Decidir antes de tener usuarios reales; hoy tres columnas apuntan al BIGINT (T-046) |
| `users.tenant_id` nullable | `users` | Si todo usuario pertenece a un tenant, pasar a NOT NULL. Hoy la base no lo impide |
| `conversations (tenant_id, user_phone)` no es único | `conversations` | La ingesta hace `firstOrCreate` sobre ese par y el lock de Redis es por mensaje y por conversación ya existente, no por teléfono. Dos primeros mensajes en ráfaga de un número nuevo podrían crear dos filas. Hay que verificarlo y, si se confirma, decidir si se vuelve único |
| Índice por tenant en `notification_logs` | `notification_logs` | El único compuesto no lidera con `tenant_id` y no hay otro compuesto por tenant |
| `quote_rules` | — | No existe. Vuelve con el cotizador (T-016, T-040, T-041) |
| Vencimiento de una derivación sin atender | `conversations` | Sin columna ni regla; la decisión sigue abierta en US-20 |
