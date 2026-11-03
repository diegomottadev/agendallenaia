---
fecha: 2026-11-03
objetivo: Sumar al flujo de trabajo lo que falta del módulo 7: mutation testing, property-based testing y comandos de QA.
estado: aprobado
---

## Pedido original

> '/home/diegomotta/Documentos/IA Assitend Dev/modulo-7-testing-calidad-con-ia/prompts' luego dime estos prompots que nos falta en nuestro workflow para agregarlo
>
> 1 hazlo y 2 con otro agente ejecutalo   (el punto 2: lo que faltaba del módulo 7)

## Prompt

```markdown
# Rol
Sos un QA senior especializado en mutation testing y property-based testing en PHP.

# Objetivo
Detectar qué prompts y prácticas del módulo 7 le faltan al flujo de AgendaLlena y agregarlos, ejecutándolo con un subagente aparte.

# Contexto
Prompts del curso: `/home/diegomotta/Documentos/IA Assitend Dev/modulo-7-testing-calidad-con-ia/prompts/01-prompts-del-modulo.md`.

# Instrucciones
1. Compará el módulo con lo que ya existe (`CLAUDE.md`, `.claude/agents`, `.claude/commands`) y listá lo que falta.
2. Encargale a un subagente la implementación:
   - Infection acotado a las clases críticas, con los mutantes sobrevivientes explicados y los tests que faltan;
   - Eris con 3 a 5 propiedades;
   - los comandos `/mutation-review`, `/analyze-risk`, `/contract-checker` y `/propiedades`, adaptados a Laravel;
   - casos borde del dominio y compuertas de OK en `tdd-test-writer`.

# Restricciones
- El subagente no toca `app/`, no modifica tests existentes, usa una base de datos propia y no commitea.

# Formato de salida
Lo que faltaba, y el informe del subagente: MSI, propiedades y archivos.
```

## Supuestos

- Se usó un subagente de propósito general en segundo plano, mientras la sesión principal arreglaba el bug del punto 1.

## Resultado

- Infection: MSI de 75,7 % a **100 %** (103 de 103 mutantes muertos), con `minCoveredMsi: 100`.
- Eris: 5 propiedades, sin contraejemplos.
- Tests nuevos: `TransicionesDeclaradasTest` y `NumeroExtranjeroLargoTest`.
- 4 comandos nuevos y secciones nuevas en `tdd-test-writer`.
- Commit `c7c24a1`.
