---
fecha: 2026-11-03
objetivo: Comparar los tests del proyecto contra los prompts del módulo 11 (E2E y QA con IA) y cerrar las brechas.
estado: aprobado
---

## Pedido original

> '/home/diegomotta/Documentos/IA Assitend Dev/modulo-11-testing-e2e-qa-con-ia/prompts'  verfiica que nos falta complemetnar en lso test
>
> (después, al plan propuesto: "ok")

## Prompt

```markdown
# Rol
Sos un QA senior especializado en Playwright y en testing asistido por IA.

# Objetivo
Verificar qué les falta a los tests de AgendaLlena según los prompts del módulo 11, y después completarlo.

# Contexto
Prompts del curso: `/home/diegomotta/Documentos/IA Assitend Dev/modulo-11-testing-e2e-qa-con-ia/prompts`.

# Instrucciones
1. Leé los prompts y comparalos con `e2e/`, `tests/Unit` y `tests/Feature`, con evidencia del código.
2. Presentá las brechas por prioridad y esperá el OK.
3. Aprobado el plan, implementá:
   - selectores accesibles;
   - oráculos de interfaz, red y estado marcados;
   - caminos de error sin `sleep`;
   - helpers o page objects;
   - estabilidad en 3 corridas seguidas;
   - tests unitarios reales verificados rompiendo el código a propósito.
4. Si un test destapa un bug, bajalo a un test de integración en rojo y consultá antes de arreglarlo.

# Restricciones
- No borres ni modifiques tests existentes sin permiso.
- Nada de `waitForTimeout`.

# Formato de salida
Tabla de brechas, después lo implementado con su evidencia.
```

## Supuestos

- El "ok" aprobó los ítems 1 a 6 del plan (alta prioridad y helpers).

## Resultado

- Helpers `ClienteDeWhatsApp` y `Panel`; E2E con selectores accesibles y oráculos marcados; `caminos-de-error.spec.ts` (colisión, rate limit, login inválido).
- Estabilidad: 9 de 9 en 3 corridas.
- 29 unitarios; 4 de 5 mutaciones los ponen en rojo.
- **Bug real:** el reintento tras un rate limit de Meta dejaba al cliente sin respuesta.
- Commit `38a97a7`.
