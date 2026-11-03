---
fecha: 2026-11-03
objetivo: Ampliar el subagente de tests con oráculos de test, sanitización y el orden E2E → integración.
estado: aprobado
---

## Pedido original

> seria bueno agregar en el agente de test los oraculos de test y satinizacion y prubeas end 2 end y luego de integracion

## Prompt

```markdown
# Rol
Sos un QA senior que diseña agentes de Claude Code para TDD.

# Objetivo
Ampliar el subagente `.claude/agents/tdd-test-writer.md` con tres contenidos nuevos:
- **Oráculos de test:** de dónde sale el valor esperado de cada afirmación.
- **Sanitización:** casos para toda entrada no confiable.
- **Niveles en orden:** primero la prueba E2E, después la de integración.

# Instrucciones
1. Explicá el orden de afuera hacia adentro: un E2E con Playwright para lo que ve el usuario, después integración y por último unitarios, con cuándo corresponde cada nivel y cómo se corre en este proyecto.
2. Definí los tipos de oráculo (especificado, calculado a mano, invariante, diferencial, de la base) y prohibí copiar el valor esperado de una corrida.
3. Sumá una tabla de sanitización para las entradas de WhatsApp, el webhook y el panel: normalización, límites, unicode, escape, inyección, autenticidad, payload deforme y fugas.
4. Actualizá el informe del agente y sus banderas rojas.

# Restricciones
- No borres nada de lo que el agente ya tiene.
- Ajustá `CLAUDE.md` para que el agente también pueda escribir en `e2e/tests/`.

# Formato de salida
Las secciones agregadas y el cambio en `CLAUDE.md`.
```

## Supuestos

- "Pruebas end 2 end y luego de integración" se interpretó como el orden de afuera hacia adentro (doble ciclo), no como dos listas sueltas.

## Resultado

`tdd-test-writer.md` con tres secciones nuevas (niveles de afuera hacia adentro, oráculos y sanitización), informe y banderas rojas ampliados. La tabla de TDD de `CLAUDE.md` ahora permite escribir en `e2e/tests/`. Commit `a4da190`.
