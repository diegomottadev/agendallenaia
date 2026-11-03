---
fecha: 2026-11-03
objetivo: Registrar todos los prompts de la conversación, reescritos con /meta-prompt, en .claude/prompts/aprobados.
estado: aprobado
---

## Pedido original

> puedes hacer un registros de todos los prompt pero primero que hayan pasado por casa /meta-prompt los promps que usamos en la conversacion y que los guardes en los prompts/aprobados

## Prompt

```markdown
# Rol
Sos el encargado de documentar el uso de IA del proyecto AgendaLlena, y conocés la convención de `.claude/prompts/aprobados/`.

# Objetivo
Registrar los prompts que el usuario usó en esta conversación con Claude Code. Cada uno se reescribe con el procedimiento de `/meta-prompt` y se guarda como prompt aprobado en `.claude/prompts/aprobados/`.

# Contexto / entrada
- Fuente: los mensajes del usuario en esta conversación, incluidos los anteriores al resumen por contexto. El transcript completo está en el `.jsonl` de la sesión.
- Convención de archivo (la de `/meta-prompt`): `.claude/prompts/aprobados/YYYY-MM-DD-<slug>.md`, con frontmatter `fecha`, `objetivo`, `estado: aprobado` y las secciones `## Pedido original`, `## Prompt`, `## Supuestos` y `## Resultado`.

# Instrucciones
1. Extraé de la conversación los mensajes del usuario que son **pedidos de trabajo**. Dejá afuera:
   - las confirmaciones sueltas («ok», «sí»);
   - los comandos de shell que corrió el usuario;
   - las respuestas a preguntas de opción múltiple.
   Agrupá en un mismo prompt los mensajes que continúan el mismo pedido.
2. Para cada pedido, aplicá el procedimiento de `/meta-prompt`:
   - estructura de rol, objetivo, contexto, instrucciones, restricciones y formato de salida;
   - sin ampliar ni recortar lo que se pidió;
   - en el idioma original;
   - con las ambigüedades resueltas y listadas como supuestos.
3. Guardá cada uno con la convención de arriba:
   - **fecha:** la del pedido;
   - **Pedido original:** el texto crudo y textual del usuario;
   - **Prompt:** la versión mejorada;
   - **Resultado:** lo que efectivamente se produjo en la conversación (archivos, commits, hallazgos).
4. Si un archivo ya existe, agregá `-2`. Nunca sobrescribas un prompt aprobado.

# Restricciones
- No inventes pedidos que el usuario no hizo, ni resultados que no ocurrieron.
- Sin secretos ni datos personales en los archivos.
- No modifiques los prompts aprobados que ya existen.

# Formato de salida
Una tabla con archivo, fecha y objetivo de cada prompt guardado, y la lista de mensajes que se descartaron con el motivo.
```

## Supuestos

- «Todos los prompts» se refiere a los pedidos de trabajo del usuario, no a cada mensaje: un «ok» o un comando de `git push` no son prompts.
- Pasarlos por `/meta-prompt` y guardarlos en `aprobados/` cuenta como su aprobación, porque el pedido es justamente archivarlos.
- Los 5 prompts reconstruidos que ya están en `aprobados/` (lean canvas, PRD, C4, DER y API) no se tocan.

## Resultado

16 archivos nuevos en `.claude/prompts/aprobados/`, este incluido. Aprobado por el usuario con «si».
