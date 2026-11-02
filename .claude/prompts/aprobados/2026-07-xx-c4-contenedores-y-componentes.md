---
fecha: 2026-07-xx
objetivo: Diagramar la arquitectura del MVP en modelo C4, nivel 2 (contenedores) y nivel 3 (componentes del backend Laravel), como dos flowcharts mermaid derivados del PRD.
estado: reconstruido
origen: ingenieria inversa de los artefactos (.claude/docs/02-arquitectura/01-c4-nivel-2-contenedores.md y 02-c4-nivel-3-componentes.md), no es el prompt original
---

## Advertencia de trazabilidad

⚠️ **Este no es el prompt que se ejecutó.** Los dos diagramas C4 se generaron antes de que existiera
`/meta-prompt`, así que su prompt no se guardó. Se reconstruyó el 2026-11-02 leyendo los dos archivos.

⚠️ **La fecha es una estimación.** Un solo commit para toda la documentación (2026-11-02). Lo seguro:
es posterior al PRD (nombra sus componentes: handshake, FreeBusy, recordatorios t-24 h/t-2 h,
Sheets) y **anterior al 2026-08-05**, cuando el prompt de story maps ya leía `02-arquitectura/`. Que
haya sido un solo prompt para los dos niveles también es inferencia: comparten vocabulario, estilo
de subgrafos y sistemas externos, pero pudieron ser dos pedidos seguidos.

⚠️ **El nivel 2 de hoy no es la salida original.** El 2026-08-25 se separó el **Scheduler** del
Worker: la caja original se llamaba *"Async Workers / Redis (State Machine, Schedulers)"* y el
comentario `%%` del encabezado lo documenta. El prompt de abajo genera la versión anterior, con una
sola caja. El nivel 3 no muestra ediciones.

**Qué es fiable y qué no:** la forma (mermaid crudo, `flowchart TB`, subgrafos por capa, etiquetas de
protocolo en las flechas, componentes con su responsabilidad en cursiva, flujo numerado 1-2-3) está
verificada contra los archivos. Los componentes son los del artefacto. El *fraseo* es inferencia.

⚠️ **Lo que dibujan estos diagramas no es lo que se construyó, y no hay que leerlos como si lo
fuera:** el *Quote Calculator Engine* no existe (cotizador diferido, T-016/T-040/T-041), el *Human
Takeover Detector* tampoco (RF-A4 se reescribió el 2026-08-20), el *REST API Gateway (CRUD Config)*
es en realidad un panel web con sesión, y el webhook de *Watch* de Google Calendar no tiene ruta en
`routes/`. Son diagramas de **diseño**, generados antes del código. El prompt reconstruido produce
eso mismo: un diseño, no un relevamiento.

**Usarlo para regenerar los C4 produciría artefactos equivalentes, no idénticos.**

## Pedido original

No conservado. Del artefacto se deduce un pedido con esta forma:

> con el PRD armá los diagramas C4 de contenedores y de componentes del backend en mermaid

## Prompt

```markdown
# Rol

Actuás como **arquitecto de software senior** con experiencia en Laravel, sistemas orientados a
eventos con colas, y modelo C4 de Simon Brown. Diagramás para que un desarrollador nuevo entienda
en un minuto qué corre dónde y por dónde entra cada request.

# Objetivo

Producir los diagramas **C4 nivel 2 (contenedores)** y **C4 nivel 3 (componentes del backend
Laravel)** del MVP descripto en el PRD, como dos flowcharts mermaid.

# Contexto / entrada

* **PRD:** `.claude/docs/01-producto/02-prd.md` — leelo entero. Los requisitos que más pesan en la
  arquitectura: RF-A1/RF-A2 (webhook de Meta con handshake, firma HMAC, 200 en < 200 ms y encolado),
  RF-A3 (máquina de estados persistida), RF-A4 (pausa por intervención humana), RF-B1 a RF-B3 (OAuth,
  FreeBusy, creación de evento y *Watch Notifications* de Google), RF-C1 (cotizador de fórmulas
  JSON), RF-D1 (scheduler cada 10 min), RF-E* (panel Vue), RNF-01 (multi-tenant por `tenant_id`).
* **Stack:** Laravel 11+ con Queues sobre Redis, Vue 3 SPA, MySQL 8, Redis (colas, estado, caché).
* **Externos:** Meta WhatsApp Cloud API, Google Calendar API, Google Sheets API.

# Instrucciones

## Nivel 2 — Contenedores

1. Cuatro subgrafos: **Sistemas Externos**, **Capa de Cliente** (la SPA Vue del tenant),
   **Laravel Backend (Multi-tenant)** y **Almacenamiento**.
2. Dentro del backend separá lo que tiene otro perfil de carga: el **REST API Gateway** del panel,
   el **Webhook Receiver** de ingesta rápida, y los **Async Workers** que corren la máquina de
   estados y los schedulers sobre Redis.
3. MySQL y Redis como nodos cilindro (`[(...)]`), cada uno con su rol entre paréntesis.
4. **Cada flecha lleva su protocolo o su verbo** (`HTTPS / JSON`, `HTTP POST Webhook`,
   `Push Job`, `Pop Job`, `OAuth / FreeBusy / Sync`, `Send Message`, `Append Lead`). Los dos webhooks
   entrantes —Meta y el *Watch* de Google— entran por el mismo receptor.

## Nivel 3 — Componentes del backend

1. Un subgrafo contenedor *Laravel Backend Application* con **cuatro capas numeradas**:
   1. **HTTP & Ingesta** — controlador del webhook (handshake + ingesta rápida) y middleware de
      firma y de *tenant scope*.
   2. **Aplicación & Colas** — worker del job de procesamiento de mensajes, detector de intervención
      humana (consulta la pausa en Redis), scheduler de notificaciones t-24 h / t-2 h.
   3. **Dominio (Core)** — motor de la máquina de estados y motor del cotizador.
   4. **Infraestructura (Adapters)** — un adapter por externo (Meta, Calendar, Sheets) y los
      repositorios Eloquent con aislamiento por `tenant_id`.
2. Un subgrafo aparte con los sistemas externos y los data stores.
3. Cada componente con su nombre y, debajo, **su responsabilidad entre paréntesis en cursiva**
   (`<br><i>(...)</i>`).
4. **Numerá el camino feliz del mensaje entrante** en las etiquetas (`1. Webhook Payload`,
   `2. Fast Push Job`, `3. Pop Job`) y agrupá las flechas con comentarios `%%` por tramo: flujo
   entrante, procesamiento asíncrono, orquestación de dominio, salida a infraestructura.
5. La máquina de estados orquesta: es la que llama al cotizador y a los adapters. El detector de
   intervención humana va **antes** de ella (`Sin Pausa Humana`).

# Restricciones

* **Mermaid crudo**, sin bloque ```` ``` ```` alrededor ni texto fuera del diagrama: cada archivo se
  renderiza directo.
* `flowchart TB` en los dos niveles. Sin estilos ni colores.
* No agregues contenedores ni componentes que el PRD no justifique.
* Nombres de componentes en inglés (son nombres de piezas de código); las responsabilidades, en
  español.

# Formato de salida

Dos archivos:

| Archivo | Contenido |
| :-- | :-- |
| `.claude/docs/02-arquitectura/01-c4-nivel-2-contenedores.md` | El flowchart de contenedores |
| `.claude/docs/02-arquitectura/02-c4-nivel-3-componentes.md` | El flowchart de componentes del backend |
```

## Supuestos de la reconstrucción

1. **Un solo prompt para los dos niveles.** Comparten nombres de externos, estilo y nivel de detalle.
   Si fueron dos pedidos, este prompt los fusiona.
2. **El receptor único para los dos webhooks fue decisión del pedido o del modelo, no se sabe.** El
   artefacto lo dibuja así en los dos niveles.
3. **La numeración de capas y del flujo fue instrucción.** Que las dos numeraciones (capas 1-4,
   pasos 1-3) coexistan sin chocar es poco probable sin un pedido explícito.
4. **La caja original del nivel 2 era una sola** (*Async Workers / Redis (State Machine,
   Schedulers)*). Se toma del comentario del 2026-08-25, que la cita.

## Resultado

Ejecutado antes del 2026-08-05 (con el prompt original, no con este). Artefactos:
`.claude/docs/02-arquitectura/01-c4-nivel-2-contenedores.md` y `02-c4-nivel-3-componentes.md`.

El nivel 2 se corrigió el 2026-08-25 al descubrir que el planificador que la caja prometía no
corría en ningún despliegue. El nivel 3 sigue siendo el de diseño.
