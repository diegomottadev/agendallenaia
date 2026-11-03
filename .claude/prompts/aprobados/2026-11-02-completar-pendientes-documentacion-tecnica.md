---
fecha: 2026-11-02
objetivo: Completar lo que faltaba de la documentación técnica de la entrega 1, con E2E, despliegue preparado y prompts reconstruidos.
estado: aprobado
---

## Pedido original

> entonces crea y agregalos y hazme preguntas si tienes dudas
>
> (sobre la lista de pendientes; seguido de: "puedes usar otro agente para ir mopdificadndo los docs?" y "ok aun no tengo hehco el domimio ni configurado para digital oceon sabes... seria en otro momento pero tenelo perparado")

## Prompt

```markdown
# Rol
Sos un desarrollador senior full stack con experiencia en QA y DevOps, y completás la documentación técnica de AgendaLlena para la entrega del máster.

# Objetivo
Crear y agregar todos los pendientes de la lista de documentación técnica.

# Instrucciones
1. Antes de empezar, preguntá lo que cambie el resultado: qué hacer con los prompts que no se guardaron, cómo simular Google, dónde desplegar, qué dominio y qué herramienta E2E.
2. Implementá cada pendiente y verificá cada uno ejecutándolo, no solo escribiéndolo.
3. Delegá a un subagente la actualización de los documentos de `.claude/docs/` contra el esquema real, en paralelo.
4. Dejá el despliegue **preparado pero apagado**: todavía no hay droplet ni dominio.

# Restricciones
- TDD para todo código nuevo. Ningún secreto en el repo.
- El despliegue no puede ejecutarse solo hasta que el usuario lo encienda.

# Formato de salida
Pendiente por pendiente: qué se hizo, cómo se verificó y qué quedó abierto.
```

## Supuestos

- Respuestas del usuario: reconstruir los prompts faltantes, simular Google Calendar, VPS con Docker en DigitalOcean, subdominio propio (todavía sin definir) y Playwright para el E2E.
- "Tenelo preparado" se interpretó como un despliegue completo pero con el CD apagado por la variable `DEPLOY_ENABLED`.

## Resultado

- Doble stateful de Google Calendar.
- E2E con Playwright del flujo principal, con capturas y video. Al correrlo encontró el bug «Sin nombre».
- Dockerfile de producción con FrankenPHP, `docker-compose.prod.yml`, workflow de CD apagado y `docs/despliegue.md`.
- 5 prompts reconstruidos en `aprobados/`.
- Un subagente actualizó el DER y el modelo de datos al esquema real, y reportó 7 contradicciones.
- Commits `0be7a11`, `3327b3b` y `606964f`; en agendallenaia, `434305f`.
