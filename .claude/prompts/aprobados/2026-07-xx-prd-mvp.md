---
fecha: 2026-07-xx
objetivo: Escribir el PRD base del MVP 1.0 (alcance IN/OUT, KPIs, requisitos funcionales RF-* por módulo, no funcionales RNF-* y matriz de criterios de aceptación CA-*) a partir del Lean Canvas.
estado: reconstruido
origen: ingenieria inversa del artefacto (.claude/docs/01-producto/02-prd.md, version 1.0), no es el prompt original
---

## Advertencia de trazabilidad

⚠️ **Este no es el prompt que se ejecutó.** El PRD se generó antes de que existiera `/meta-prompt`,
así que su prompt no se guardó. Se reconstruyó el 2026-11-02 leyendo el artefacto.

⚠️ **La fecha es una estimación.** Un solo commit para toda la documentación (2026-11-02), sin
historia. Lo seguro: es posterior al Lean Canvas, del que hereda cifras, y **anterior al
2026-08-05**, cuando el prompt de user stories ya copiaba su alcance casi literal. Se pone
`2026-07-xx` porque el día no se puede deducir.

⚠️ **El PRD de hoy no es la salida de este prompt.** Lo que este prompt produce es la **versión 1.0
base**. Encima de ella hubo ediciones a mano o con otros prompts que **no hay que atribuirle**:

| Edición posterior | Cómo se reconoce |
| :-- | :-- |
| Decisiones "cerradas (T-007)" en RF-A3, RNF-01 y el OUT-OF-SCOPE de Outlook | Citan T-007, un ticket que nace el 2026-08-05 y se cierra el 2026-08-19 |
| **RF-A4 reescrito** el 2026-08-20 (modelo de dos números) | Lo dice el propio texto, que cita la versión anterior |
| Bullet de IN-SCOPE "Pausa **manual**..." | Lleva la nota ⚠️ con lo que decía antes |
| RF-A6, RF-D3, RF-D4, RF-E3 y el **Módulo F** entero (RF-F1) | Marcados "BORRADOR, pendiente de aprobación"; cubren US-11, US-13, US-15, US-16, US-17 y US-26, que la matriz del story map marca "sin RF" o nacieron de sus huecos |
| Notas ⚠️ sobre T-018, CA-02 y la decisión 4.2 | Citan tickets y story maps |

**Qué es fiable y qué no:** la estructura (encabezado de metadatos, cinco secciones, módulos A a E,
convención de IDs, tabla de CA) está verificada contra el archivo. El contenido de los requisitos
base se lee del archivo restando las ediciones de arriba; donde una edición reescribió un texto, el
original se toma de la cita que la propia edición deja (RF-A4, el bullet de pausa) o del alcance que
copia el prompt de user stories del 2026-08-05. El *fraseo* de las instrucciones es inferencia.

**Usarlo para regenerar el PRD produciría un 1.0 equivalente, no idéntico, y sin ninguna de las
decisiones que se tomaron después.**

## Pedido original

No conservado. Del artefacto se deduce un pedido con esta forma:

> a partir del lean canvas armá el PRD del MVP: alcance, fuera de alcance, KPIs, requisitos
> funcionales por módulo, no funcionales y criterios de aceptación

## Prompt

```markdown
# Rol

Actuás como **Senior Product Manager técnico** con experiencia en SaaS B2B multi-tenant y en
integraciones con la API oficial de WhatsApp (Meta Cloud API) y Google Workspace. Escribís PRDs que
un equipo de desarrollo puede tomar sin reunión previa.

# Objetivo

Escribir el **PRD del MVP Release 1.0** del producto descripto abajo, listo para pasar a
desarrollo: qué entra, qué no, cómo se mide, qué tiene que hacer el sistema y cómo se verifica que
lo hace.

# Contexto / entrada

* **Producto:** middleware SaaS multi-tenant de agendamiento y cotización por WhatsApp para PyMEs
  latinoamericanas de 3 a 20 empleados. Fuente: el Lean Canvas en
  `.claude/docs/01-producto/01-lean-canvas.md`.
* **Propuesta de valor:** demostrar ROI en la primera llamada comercial — eliminar el ausentismo y
  agendar en menos de 15 segundos sin que la PyME cambie de herramientas (sigue en Google Calendar y
  Google Sheets).
* **Stack objetivo:** Laravel 11+ (motor + colas con Redis), Vue.js 3 (panel admin), Meta Cloud API,
  Google Calendar API.
* **Modelo de negocio:** setup USD 100-300 + abono USD 80-150/mes, tarifa plana por PyME.
* **Lo que el MVP tiene que cubrir:** handshake del webhook de Meta; ingesta asíncrona con máquina
  de estados; respuestas interactivas (botones y listas); pausa automática del bot por 60 min cuando
  un operador responde a mano desde el WhatsApp del negocio; OAuth 2.0 con Google Calendar,
  disponibilidad en tiempo real y sincronización inversa; cotizador paramétrico simple
  (`Precio = Base + Variable × Valor`); recordatorios interactivos a t-24 h y t-2 h (Confirmar /
  Cancelar / Re-agendar); zonas horarias por PyME; volcado de leads a Google Sheets; panel mínimo;
  mensajes de *fallback* ante caídas de terceros.
* **Lo que explícitamente no:** cobro de señas, editor visual de flujos, CRM propio o integración con
  CRMs pesados, múltiples sucursales, librerías no oficiales de WhatsApp.

# Instrucciones

1. **Encabezado de metadatos** debajo del título: Versión, Estado, Stack Objetivo, Modelo de Negocio,
   en negrita y uno por línea.
2. **§1 Visión y Alcance.** Un párrafo con *el único propósito* del MVP. Después dos listas:
   **IN-SCOPE** y **OUT-OF-SCOPE explícito**, esta última con cada ítem marcado `❌`. Lo que queda
   afuera se nombra: un fuera de alcance implícito es un alcance que alguien va a prometer.
3. **§2 KPIs**, partidos en *Impacto en el cliente* (tiempo de respuesta, asistencia, conversión
   chat-a-reserva) y *Métricas del producto* (tiempo de setup, latencia de ingesta). Cada uno con
   umbral numérico.
4. **§3 Requisitos funcionales, agrupados en módulos con letra:**
   * **A** · Motor de WhatsApp y máquina de estados
   * **B** · Integración Google Calendar y motor de disponibilidad
   * **C** · Cotizador paramétrico
   * **D** · Recordatorios temporizados
   * **E** · Panel admin (Vue 3)

   IDs `RF-<módulo><n>` (`RF-A1`, `RF-B3`), cada uno con nombre entre paréntesis en negrita y una o
   dos oraciones. Concretos: método y ruta del endpoint (`/api/v1/webhooks/whatsapp`), algoritmo de
   firma, umbral de latencia, nombre de la tabla, frecuencia del cron. Los estados de la máquina se
   nombran en `MAYUSCULAS_CON_GUION`.
5. **§4 Requisitos no funcionales** `RNF-01` a `RNF-04`: multi-tenancy por columna `tenant_id`,
   almacenamiento en UTC con conversión a la zona del tenant en el borde, *fallback* con mensaje de
   cortesía textual ante caída de un tercero o token vencido, y latencia interna punta a punta.
6. **§5 Matriz de criterios de aceptación (Definition of Done)**: tabla
   `ID | Requisito | Prueba de Validación | Criterio de Pase`, IDs `CA-01` en adelante. Cada fila es
   una prueba **ejecutable por una persona** (registrar la URL en Meta, forzar la fecha del servidor,
   invalidar credenciales) con un criterio de pase observable.
7. Fórmulas y tiempos en notación LaTeX inline (`$t-24\text{ hs}$`). Los términos técnicos en inglés
   van en cursiva (*Fallback*, *Timezones*, *buffers*).

# Restricciones

* No inventes integraciones, pantallas ni tablas fuera de lo que lista el contexto.
* Un requisito por ID; si un requisito tiene dos comportamientos, son dos IDs.
* Español rioplatense, registro técnico. Sin introducción ni cierre fuera de las cinco secciones.

# Formato de salida

Un único archivo `.claude/docs/01-producto/02-prd.md`, con título
`# PRD: <nombre del producto> (MVP Release 1.0)`, el encabezado de metadatos, las cinco secciones
numeradas separadas por `---`, y la tabla de CA como cierre.
```

## Supuestos de la reconstrucción

1. **El 1.0 tenía cinco módulos, A a E.** El Módulo F (Google Sheets) está todo en borrador y cubre
   US-13/US-26: Sheets estaba en el IN-SCOPE **sin requisito funcional**, y la matriz de
   trazabilidad del story map marca US-13 como "sin RF". Si el 1.0 ya tenía RF-F1, este
   supuesto es falso.
2. **RF-A4 original pedía detección automática de la respuesta manual**, y CA-03 lo prueba así. Se
   toma de la cita textual que deja la reescritura del 2026-08-20. Es lo que la plataforma no permite:
   el prompt reconstruido lo pide igual porque el original lo pedía.
3. **RF-A3 nombraba cuatro estados** (`IDLE`, `GATHERING_PARAMS`, `SELECTING_SLOT`, `BOOKED`). Lo dice
   la decisión de T-007, que los corrige a nueve.
4. **La tabla de CA venía en el pedido.** "Definition of Done" como subtítulo y las pruebas manuales
   ejecutables son un formato demasiado específico para emerger solo.

## Resultado

Ejecutado antes del 2026-08-05 (con el prompt original, no con este). Artefacto:
`.claude/docs/01-producto/02-prd.md`, versión 1.0: módulos A a E (RF-A1…A5, RF-B1…B3, RF-C1,
RF-D1…D2, RF-E1…E2), RNF-01…04 y CA-01…06.

Es el documento más editado del repositorio después: T-007 cerró cinco decisiones encima, RF-A4 se
reescribió entero, y después de los story maps se le agregaron cinco requisitos en borrador y un
módulo para tapar los "sin RF" de la matriz de trazabilidad (`stories-maps/05-huecos-y-trazabilidad.md`
marca US-13, US-15, US-16 y US-17 así). No consta con qué prompt se hizo esa edición.
