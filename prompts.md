> Detalla en esta sección los prompts principales utilizados durante la creación del proyecto, que justifiquen el uso de asistentes de código en todas las fases del ciclo de vida del desarrollo. Esperamos un máximo de 3 por sección, principalmente los de creación inicial o  los de corrección o adición de funcionalidades que consideres más relevantes.
Puedes añadir adicionalmente la conversación completa como link o archivo adjunto si así lo consideras

**Cómo se trabajó.** El asistente fue Claude Code. Cada prompt importante pasó por el comando [`/meta-prompt`](.claude/commands/meta-prompt.md), que lo reescribe con rol, objetivo, contexto y formato de salida sin cambiar lo que pide. Solo se guardó en [`.claude/prompts/aprobados/`](.claude/prompts/aprobados/) una vez revisado el resultado. Por eso cada entrada de abajo tiene dos partes: el **pedido original** (mis palabras) y el **prompt** que se ejecutó (el link al archivo completo).

La cadena de derivación es **PRD → story maps → historias → tickets → tests → código**, con un agente por eslabón ([`.claude/agents/`](.claude/agents/)), y cada paso se traza al anterior por ID.

## Índice

1. [Descripción general del producto](#1-descripción-general-del-producto)
2. [Arquitectura del sistema](#2-arquitectura-del-sistema)
3. [Modelo de datos](#3-modelo-de-datos)
4. [Especificación de la API](#4-especificación-de-la-api)
5. [Historias de usuario](#5-historias-de-usuario)
6. [Tickets de trabajo](#6-tickets-de-trabajo)
7. [Pull requests](#7-pull-requests)

---

## 1. Descripción general del producto

**Prompt 1: recorte del MVP con Pareto** · [`2026-08-18-pareto-sobre-tickets-mvp.md`](.claude/prompts/aprobados/2026-08-18-pareto-sobre-tickets-mvp.md)

> Pedido original (resumido): *"teniendo en cuenta las historias de usuario [...] no modificar pero solamente analizarlas para aplicar el principio de pareto en todos los tickets [...] para tener mejores resultados"*

```markdown
# Rol
Adoptá la persona definida en `.claude/prompts/aprobados/2026-08-18-arquitecto-pragmatico-pareto.md` [...]
# Objetivo
Aplicar el principio de Pareto (80/20) a los 49 tickets de `.claude/docs/tickets/`, usando las 26 historias
de usuario de `.claude/docs/stories/` como fuente de verdad del valor que cada ticket entrega, para obtener un
MVP que entregue el ~80% del valor prometido con el ~20% del esfuerzo comprometido.
```

*Cómo guié al asistente:* las historias eran la medida del valor y no se podían modificar. Cada ticket tenía que quedar como NÚCLEO o DIFERIDO, con el argumento escrito, y la deuda declarada. Resultado: [`03-analisis-pareto.md`](.claude/docs/estimacion-backlog/03-analisis-pareto.md), que define qué entra en el MVP.

**Prompt 2: decisiones de producto por preguntas cerradas** · [`2026-08-21-decisiones-tomadas-plan-for-diego.md`](.claude/prompts/aprobados/2026-08-21-decisiones-tomadas-plan-for-diego.md)

> Pedido original (resumido): *"vamos a armar el plan en base a preguntas y vos me das las opciones para responder de acuerdo a lo hay de documentos y tickets [...] todas las respuesta la cargamos en plan for diego en un archivo que se llama decisiones tomadas"*

*Cómo guié al asistente:* le pedí que **no decidiera por mí**. Tenía que presentar una decisión abierta por vez con opciones derivadas de los documentos (no inventadas), registrar mi respuesta y actualizar el plan. Resultado: [`decisiones-tomadas.md`](.claude/docs/plan-for-diego/decisiones-tomadas.md).

**Prompt 3: persona de arquitecto pragmático** · [`2026-08-18-arquitecto-pragmatico-pareto.md`](.claude/prompts/aprobados/2026-08-18-arquitecto-pragmatico-pareto.md)

```markdown
# Role: Senior Pragmatic Software Architect & Product Engineer
Apply the Pareto Delivery Principle (80/20 Rule) to every solution, architecture proposal, and code snippet.
[...] PREFER: existing framework capabilities, minimal files/dependencies [...]
REJECT: premature optimization, generic abstractions, speculative flexibility [...]
```

*Cómo guié al asistente:* es una persona reutilizable que los demás prompts adoptan "verbatim". Le agregué una sección de resultados obligatoria: qué se hace, qué se difiere y por qué.

**Prompts de creación del lean canvas y del PRD** (reconstruidos)

> ⚠️ **Reconstruidos, no son los originales.** El [lean canvas](.claude/docs/01-producto/01-lean-canvas.md) y el [PRD](.claude/docs/01-producto/02-prd.md) se escribieron antes de que existiera la carpeta `aprobados/`, y esas conversaciones no se conservaron. Los prompts de abajo se reconstruyeron el 2026-11-02 por ingeniería inversa de cada artefacto. Cada archivo explica qué parte es fiable (la estructura de salida) y qué es inferencia (la redacción).

- [`2026-07-xx-lean-canvas.md`](.claude/prompts/aprobados/2026-07-xx-lean-canvas.md)
  ```markdown
  # Rol
  Actuás como estratega de producto y emprendedor serial con experiencia en Lean Startup y en vender
  software B2B a PyMEs latinoamericanas [...]
  # Objetivo
  Producir el Lean Canvas de un SaaS multi-tenant de agendamiento y cotización por WhatsApp para PyMEs,
  como un único diagrama mermaid [...] que renderice los nueve bloques del canvas.
  ```
- [`2026-07-xx-prd-mvp.md`](.claude/prompts/aprobados/2026-07-xx-prd-mvp.md)
  ```markdown
  # Rol
  Actuás como Senior Product Manager técnico con experiencia en SaaS B2B multi-tenant y en integraciones
  con la API oficial de WhatsApp (Meta Cloud API) y Google Workspace [...]
  # Objetivo
  Escribir el PRD del MVP Release 1.0 [...]: qué entra, qué no, cómo se mide, qué tiene que hacer el
  sistema y cómo se verifica que lo hace.
  ```

*Cómo guié al asistente:* el PRD salió del lean canvas y fijó los IDs (`RF-*`, `RNF-*`, `CA-*`) que después usan todas las historias y los tickets. Las "decisiones cerradas (T-007)" y las reescrituras que hoy tiene el PRD son ediciones posteriores, hechas a medida que la construcción encontraba contradicciones; no salieron de este prompt.

---

## 2. Arquitectura del Sistema

### **2.1. Diagrama de arquitectura:**

**Prompt 1: C4 nivel 2 y 3** (reconstruido) · [`2026-07-xx-c4-contenedores-y-componentes.md`](.claude/prompts/aprobados/2026-07-xx-c4-contenedores-y-componentes.md)

> ⚠️ Reconstruido por ingeniería inversa de los diagramas [de contenedores](.claude/docs/02-arquitectura/01-c4-nivel-2-contenedores.md) y [de componentes](.claude/docs/02-arquitectura/02-c4-nivel-3-componentes.md). El original no se guardó.

```markdown
# Rol
Actuás como arquitecto de software senior con experiencia en Laravel, sistemas orientados a eventos con
colas, y modelo C4 de Simon Brown. Diagramás para que un desarrollador nuevo entienda en un minuto qué
corre dónde y por dónde entra cada request.
# Objetivo
Producir los diagramas C4 nivel 2 (contenedores) y C4 nivel 3 (componentes del backend Laravel) del MVP
descripto en el PRD, como dos flowcharts mermaid.
```

*Cómo guié al asistente:* los diagramas tenían que salir del PRD, en particular de RF-A2 (responder `200` antes de procesar) y RNF-01 (multi-tenant). El 2026-08-25 corregí el C4 nivel 2 a mano: el scheduler figuraba dentro del worker y en realidad **no corría en ningún despliegue**. Lo separé en su propia caja.

### **2.2. Descripción de componentes principales:**

**Prompt 1: estándar de diseño como skill** · [`2026-08-18-skill-principios-diseno-software.md`](.claude/prompts/aprobados/2026-08-18-skill-principios-diseno-software.md)

> Pedido original (resumido): *"crea un skill con esto optimizando # SKILL: Software Design Principles & Code Quality Standard [...] KISS, YAGNI, DRY, SOLID [...]"*

*Cómo guié al asistente:* le pedí convertir una lista genérica de principios en un **skill operable**, con reglas de decisión concretas para escribir y refactorizar. Se usa en cada ticket junto con los skills del dominio: [`laravel-agendallena`](.claude/skills/laravel-agendallena/SKILL.md), [`meta-cloud-api`](.claude/skills/meta-cloud-api/SKILL.md) y [`google-calendar`](.claude/skills/google-calendar/SKILL.md).

### **2.3. Descripción de alto nivel del proyecto y estructura de ficheros**

**Prompt 1: estudiar el proyecto y levantarlo** · conversación con Claude Code del 2026-11-02

> Pedido original: *"estudia el proyecto y que tecnologias usa y levantolo con las imagenes de docker que tenemos disponibles salvo que no concidia su version y lo mismo la db"*

*Cómo guié al asistente:* le pedí que **verificara versiones** en lugar de suponerlas. Reutilizó la imagen local de PHP 8.4 porque coincidía, y bajó MySQL 8.4 y Redis 7 porque las locales eran 8.0 y 8.10. De ese relevamiento salieron el mapa de carpetas y de componentes de las secciones 2.2 y 2.3 del readme.

### **2.4. Infraestructura y despliegue**

**Prompt 1: despliegue preparado en DigitalOcean** · conversación con Claude Code del 2026-11-02

> Pedido original: elegí *"VPS con Docker"* sobre DigitalOcean, con subdominio propio, y aclaré: *"aun no tengo hecho el dominio ni configurado para digital ocean [...] seria en otro momento pero tenelo preparado"*

*Cómo guié al asistente:* le pedí que lo dejara **listo y apagado**: un CD que no corre hasta que una variable lo encienda, para no tener builds rojos mientras no exista el servidor. Al construir la imagen de producción detectó que la tzdata de la base era vieja (2025.3 contra el mínimo de 2026.3 que exige la suite de husos) y la resolvió con `timezonedb`, que llevó la tzdata a 2026.6. Resultado: [`docs/despliegue.md`](docs/despliegue.md).

### **2.5. Seguridad**

Las reglas de seguridad (firma HMAC, aislamiento por tenant, tokens encriptados, secretos fuera de los logs) son invariantes del proyecto escritos en [`CLAUDE.md`](CLAUDE.md). El agente [`invariantes-auditor`](.claude/agents/invariantes-auditor.md) las verifica en cada cambio de código.

**Prompt 1: simulador sin abrir una puerta en producción** · conversación con Claude Code del 2026-11-02

> Pedido original: *"hay forma de mokear lo de whastapp api clound mientras se desarrolla? para no usar directamente"*

*Cómo guié al asistente:* pedí que lo simulado pasara por el mismo camino que un mensaje real, con firma HMAC, cola y worker. A partir de ahí, la condición de seguridad fue que un endpoint capaz de firmar webhooks con el secreto de Meta **no puede existir en producción**. Por eso `SoloConSimulador` devuelve `404` siempre con `APP_ENV=production`, aunque la variable quede encendida por error, y hay tests que lo verifican.

⚠️ **Pendiente:** el prompt original con el que se definieron los invariantes de `CLAUDE.md` no se guardó.

### **2.6. Tests**

**Prompt 1: agentes de TDD con Pareto** · [`2026-08-18-pareto-sobre-agentes.md`](.claude/prompts/aprobados/2026-08-18-pareto-sobre-agentes.md)

> Pedido original (resumido): *"aplica el principio de pareto para aplicar y tener el 80% de los resultados con el 20% de esfuerzos en cada agente"*

*Cómo guié al asistente:* los agentes [`tdd-test-writer`](.claude/agents/tdd-test-writer.md), [`tdd-implementer`](.claude/agents/tdd-implementer.md) y [`tdd-refactorer`](.claude/agents/tdd-refactorer.md) trabajan **cada uno limitado a su fase**: el que escribe el test no implementa, y el que implementa no toca el test. El prompt recortó lo que cada agente revisaba para quedarse con los hallazgos que más valen. Le recordé que la `description` está siempre en contexto y que el cuerpo solo se carga al despacharlo, así que tenía que ser corta.

**Prompt 2: E2E del flujo principal con Playwright** · conversación con Claude Code del 2026-11-02

> Pedido original: elegí *"Playwright"* para el test E2E de navegador y *"Simular Google"* para que el flujo completo corra en local.

*Cómo guié al asistente:* el E2E tenía que recorrer el flujo **como lo vive cada actor**: el cliente reserva por WhatsApp y la dueña ve el turno en el panel, sin atajos por la base. El mismo test genera las capturas y el video del readme, así la evidencia se regenera con un comando. Al correrlo apareció un **bug real**: el panel muestra "Sin nombre" porque el nombre del cliente no se copia a `conversations.user_name`. Le pedí que no lo tapara en el test: quedó documentado y pendiente de corregir con TDD.

---

## 3. Modelo de Datos

**Prompt 1: DER y modelo de datos** (reconstruido) · [`2026-07-xx-der-y-modelo-de-datos.md`](.claude/prompts/aprobados/2026-07-xx-der-y-modelo-de-datos.md)

> ⚠️ Reconstruido por ingeniería inversa de la **versión original** del [DER](.claude/docs/02-arquitectura/03-der-entidad-relacion.md) y del [modelo de datos](.claude/docs/02-arquitectura/04-modelo-de-datos.md). El original no se guardó.

```markdown
# Rol
Actuás como arquitecto de datos senior con experiencia en MySQL 8, Laravel Eloquent y SaaS multi-tenant
de base compartida. Diseñás pensando en el índice que va a usar cada consulta caliente y en que una PyME
nunca vea los datos de otra.
# Objetivo
Diseñar el modelo de datos del MVP y entregarlo en dos documentos: el diagrama entidad-relación en
mermaid y el modelo de datos técnico con principios y DDL ejecutable.
```

**Prompt 2: llevar el modelo al esquema real** · conversación con Claude Code del 2026-11-02

> Pedido original, sobre la lista de pendientes de la entrega (que incluía el DER y `04-modelo-de-datos.md` desactualizados): *"entonces crea y agregalos y hazme preguntas si tienes dudas"*, y después *"puedes usar otro agente para ir modificando los docs?"*

*Cómo guié al asistente:* la fuente de verdad tenía que ser **la base corriendo** (`SHOW CREATE TABLE` e `information_schema`) y no el diseño. Un subagente limitado a dos carpetas actualizó el DER y el DDL citando el ticket de cada columna. Además informó, sin corregirlas, las contradicciones que encontró: `conversations (tenant_id, user_phone)` no es único, a `notification_logs` le falta un índice que lidere con `tenant_id`, y el C4 dibuja piezas diferidas.

---

## 4. Especificación de la API

**Prompt 1: OpenAPI de los endpoints públicos** · [`2026-11-02-especificacion-api.md`](.claude/prompts/aprobados/2026-11-02-especificacion-api.md)

> No hubo un documento de API previo: la especificación se escribió para esta entrega a partir del código. El archivo deja registrado el prompt equivalente.

```markdown
# Rol
Actuás como ingeniero backend senior y redactor de contratos de API, con experiencia en OpenAPI 3,
webhooks de terceros y flujos OAuth 2.0. Documentás lo que el código hace, no lo que el diseño prometía.
# Objetivo
Escribir la especificación OpenAPI 3.0 de los endpoints públicos del backend de AgendaLlena [...] con un
ejemplo de petición y respuesta por endpoint.
```

*Cómo guié al asistente:* "lo que el código hace, no lo que el diseño prometía". Antes de escribir los códigos de respuesta se verificaron en el código: la firma inválida devuelve `401`, y un `state` inválido de OAuth **no** devuelve `403` sino una redirección `302` al panel con el motivo.

---

## 5. Historias de Usuario

**Prompt 1: historias con requisitos técnicos** · [`2026-08-05-user-stories-con-requisitos-tecnicos.md`](.claude/prompts/aprobados/2026-08-05-user-stories-con-requisitos-tecnicos.md)

> Pedido original (resumido): *"adapta tmb para que agregue [...] Dadas las historias de usuario como una parte más de la descripción de la historia de usuario, ¿qué requisitos técnicos se necesitarían?"*

```markdown
Actuá como un Senior Product Owner y Strategist de Producto con amplia experiencia en metodologías ágiles,
Scrum, Lean Startup y arquitectura de software.
[...] Entregable: [N] user stories priorizadas por riesgo, en formato clásico (Como/Quiero/Para) con
Criterios de Aceptación BDD, Requisitos Técnicos, Estimación y Evaluación INVEST.
```

*Cómo guié al asistente:* le pedí que los requisitos técnicos fueran **parte de cada historia** y no un documento aparte, y que cada historia se evaluara con INVEST con un veredicto honesto. Varias quedaron en 2 de 6, y eso fue lo que motivó partirlas en tickets.

**Prompt 2: story maps cruzando toda la documentación** · [`2026-08-05-story-maps-mvp-desde-documentacion.md`](.claude/prompts/aprobados/2026-08-05-story-maps-mvp-desde-documentacion.md)

> Pedido original (resumido): *"ejecuta meta-prompt.md sobre generar-user-stories-mvp.md, ejecuta y revisa todo 01-producto, 02-arquitectura, 03-flujos y crea las stories maps"*

*Cómo guié al asistente:* el objetivo no era dibujar mapas sino **cruzar el backlog contra el PRD, el C4, el DER y los flujos** para encontrar huecos y contradicciones. Resultado: [21 huecos y una matriz de trazabilidad](.claude/docs/stories-maps/05-huecos-y-trazabilidad.md) y el [corte por slices](.claude/docs/stories-maps/04-slices-y-secuencia.md).

**Prompt 3: comando `/requisitos-tecnicos`** · [`.claude/commands/requisitos-tecnicos.md`](.claude/commands/requisitos-tecnicos.md)

```markdown
# Rol
Actuá como Arquitecto de Software / Tech Lead con experiencia en SaaS multi-tenant, integraciones con APIs
de terceros y sistemas orientados a eventos.
# Objetivo
Dadas las historias de usuario del backlog, determinar qué requisitos técnicos se necesitarían para poder
construirlas. [...] El entregable son los requisitos, no el diseño.
```

*Cómo guié al asistente:* lo convertí en un comando reutilizable que recibe un alcance (una historia, una sección o todo el backlog) y señala los huecos contra lo que **ya existe** en el PRD y la arquitectura.

---

## 6. Tickets de Trabajo

**Prompt 1: tickets desde las historias** · [`2026-08-07-generar-tickets-desde-stories.md`](.claude/prompts/aprobados/2026-08-07-generar-tickets-desde-stories.md)

> ⚠️ **Este prompt está reconstruido, no es el original.** Los tickets se generaron el 2026-08-05 con un prompt que nunca se aprobó y por eso no se guardó. El 2026-08-07 se reconstruyó por ingeniería inversa del artefacto, y el archivo lo declara.

```markdown
revisá `.claude/docs/stories/` y generá los tickets de trabajo en `.claude/docs/tickets/`,
auditando INVEST y partiendo las historias que no cumplen
```

*Cómo guié al asistente:* cada ticket tiene que corregir la falla INVEST de su historia: contexto, alcance, criterios verificables con referencia al AC de origen, INVEST por ticket, fuera de alcance, dependencias y story points.

**Prompt 2: Pareto sobre los tickets** · ver [sección 1, Prompt 1](#1-descripción-general-del-producto). Además de recortar el MVP, ordenó los tickets en olas y dejó los diferidos en [`tickets/Next-iteraction/`](.claude/docs/tickets/Next-iteraction/).

**Prompt 3: corregir lo que destaparon el E2E y la revisión del modelo** · conversación con Claude Code del 2026-11-03

> Pedido original: *"sí, corregí el bug y los índices"*, y a mitad de camino: *"seria bueno agregar en el agente de test los oraculos de test y satinizacion y prubeas end 2 end y luego de integracion"*.

*Cómo guié al asistente:* cada defecto con su test en rojo antes del código (el bug «Sin nombre», el único en `conversations` y el índice de `notification_logs`), y el arreglo verificado por mutación. Cuando el índice único rompió nueve tests porque sus fixtures fabricaban varias conversaciones del mismo teléfono, el asistente frenó y me pidió permiso antes de tocarlos, como exige `CLAUDE.md`. El E2E pasó a afirmar el nombre en el panel. Además amplié el subagente `tdd-test-writer`: ahora trabaja de afuera hacia adentro (E2E con Playwright → integración → unitario), nombra el **oráculo** de cada afirmación y suma casos de **sanitización** para toda entrada no confiable (WhatsApp, webhook, panel).

---

## 7. Pull Requests

**Prompt 1: PR de la entrega 1** · conversación con Claude Code del 2026-11-02 · [PR #1](https://github.com/diegomottadev/agendallenaia/pull/1)

> Pedido original: *"necesito crear una rama en https://github.com/diegomottadev/agendallenaia que sea para la documentacion tecnica donde no vaya el codigo del proyecto si no todo lo de .claude y lo que aun falta para la parte de documentacion tecnica"*, y después *"abrí el PR vos"*.

*Cómo guié al asistente:* la rama sigue la convención de la consigna (`feature-entrega1-DM`). Antes de publicar en un repo público le pedí que revisara que `.claude/` no tuviera secretos. La descripción del PR sigue lo que pide la consigna (qué cambia, por qué, impacto) y suma una checklist de pendientes.
