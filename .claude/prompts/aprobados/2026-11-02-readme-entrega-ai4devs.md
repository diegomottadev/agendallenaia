---
fecha: 2026-11-02
objetivo: Armar el readme del repo de entrega con la estructura y el contenido que pide la plantilla del curso.
estado: aprobado
---

## Pedido original

> ok necesito que armes bien el readme del proyecto para agendallenaia como pide la consigna

## Prompt

```markdown
# Rol
Sos un technical writer que conoce la plantilla `AI4Devs-finalproject` de LIDR.

# Objetivo
Armar el `readme.md` de `agendallenaia` tal como lo pide la consigna del proyecto final.

# Contexto
- La consigna está en `consigna.md` y la plantilla en el `readme.md` original del repo.
- Fuentes: `.claude/docs/`, el código, el esquema real de la base, los tests y las capturas del E2E.

# Instrucciones
1. Respetá las secciones de la plantilla, de la 0 a la 7: ficha, producto, arquitectura, modelo de datos, API, historias, tickets y PRs.
2. Cada sección, con contenido real del proyecto y verificable contra el código.
3. Diagramas en Mermaid, el modelo de datos del esquema real y la API en OpenAPI.
4. Marcá con ⚠️ lo que todavía no existe (por ejemplo, la URL pública), en vez de inventarlo.

# Restricciones
- No inventes datos ni endpoints.

# Formato de salida
`readme.md` completo y un resumen de qué tiene cada sección.
```

## Supuestos

- Las historias y tickets elegidos como representativos (US-03, US-05, US-10; T-022, T-047, T-048) son los del flujo principal.

## Resultado

`readme.md` de `agendallenaia` con las secciones 0 a 7 completas: capturas y GIF, instalación con el simulador, C4, el diagrama de entidades con las 11 tablas reales, OpenAPI de 3 endpoints, historias, tickets y el PR #1 documentado.
