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

⚠️ **Pendiente:** los prompts de creación del [lean canvas](.claude/docs/01-producto/01-lean-canvas.md) y del [PRD](.claude/docs/01-producto/02-prd.md) no se guardaron. Hay que recuperarlos del historial de conversaciones o reconstruirlos, marcando la reconstrucción como tal.

---

## 2. Arquitectura del Sistema

### **2.1. Diagrama de arquitectura:**

⚠️ **Pendiente:** el prompt con el que se generaron los C4 de nivel 2 y 3 ([contenedores](.claude/docs/02-arquitectura/01-c4-nivel-2-contenedores.md), [componentes](.claude/docs/02-arquitectura/02-c4-nivel-3-componentes.md)) no se guardó en `aprobados/`.

### **2.2. Descripción de componentes principales:**

**Prompt 1: estándar de diseño como skill** · [`2026-08-18-skill-principios-diseno-software.md`](.claude/prompts/aprobados/2026-08-18-skill-principios-diseno-software.md)

> Pedido original (resumido): *"crea un skill con esto optimizando # SKILL: Software Design Principles & Code Quality Standard [...] KISS, YAGNI, DRY, SOLID [...]"*

*Cómo guié al asistente:* le pedí convertir una lista genérica de principios en un **skill operable**, con reglas de decisión concretas para escribir y refactorizar. Se usa en cada ticket junto con los skills del dominio: [`laravel-agendallena`](.claude/skills/laravel-agendallena/SKILL.md), [`meta-cloud-api`](.claude/skills/meta-cloud-api/SKILL.md) y [`google-calendar`](.claude/skills/google-calendar/SKILL.md).

### **2.3. Descripción de alto nivel del proyecto y estructura de ficheros**

⚠️ **Pendiente.**

### **2.4. Infraestructura y despliegue**

⚠️ **Pendiente:** se completa con la entrega final, cuando exista el despliegue público.

### **2.5. Seguridad**

⚠️ **Pendiente.** Las reglas de seguridad (firma HMAC, aislamiento por tenant, tokens encriptados) están en [`CLAUDE.md`](CLAUDE.md), y el agente [`invariantes-auditor`](.claude/agents/invariantes-auditor.md) las verifica en cada cambio. Falta documentar el prompt con el que se definieron.

### **2.6. Tests**

**Prompt 1: agentes de TDD con Pareto** · [`2026-08-18-pareto-sobre-agentes.md`](.claude/prompts/aprobados/2026-08-18-pareto-sobre-agentes.md)

> Pedido original (resumido): *"aplica el principio de pareto para aplicar y tener el 80% de los resultados con el 20% de esfuerzos en cada agente"*

*Cómo guié al asistente:* los agentes [`tdd-test-writer`](.claude/agents/tdd-test-writer.md), [`tdd-implementer`](.claude/agents/tdd-implementer.md) y [`tdd-refactorer`](.claude/agents/tdd-refactorer.md) trabajan **cada uno limitado a su fase**: el que escribe el test no implementa, y el que implementa no toca el test. El prompt recortó lo que cada agente revisaba para quedarse con los hallazgos que más valen. Le recordé que la `description` está siempre en contexto y que el cuerpo solo se carga al despacharlo, así que tenía que ser corta.

---

## 3. Modelo de Datos

⚠️ **Pendiente:** el prompt del [DER](.claude/docs/02-arquitectura/03-der-entidad-relacion.md) y del [modelo de datos](.claude/docs/02-arquitectura/04-modelo-de-datos.md) no se guardó. Además, esos documentos quedaron detrás del esquema real; el `readme.md` de esta entrega trae el diagrama al día.

---

## 4. Especificación de la API

⚠️ **Pendiente.**

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

---

## 7. Pull Requests

⚠️ **Pendiente:** hasta ahora no hubo PRs. A partir de esta entrega cada cambio entra por pull request, y acá van los prompts usados para redactarlos o revisarlos.
