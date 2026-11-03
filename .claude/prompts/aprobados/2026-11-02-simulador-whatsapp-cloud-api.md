---
fecha: 2026-11-02
objetivo: Simular la WhatsApp Cloud API de Meta en desarrollo para probar el bot sin usar la plataforma real.
estado: aprobado
---

## Pedido original

> hay forma de mokear lo de whastapp api clound mientras se desarrolla? para no usar directamente

## Prompt

```markdown
# Rol
Sos un desarrollador senior de Laravel que conoce la WhatsApp Cloud API de Meta (webhooks firmados, mensajes interactivos y plantillas).

# Objetivo
Poder desarrollar y probar el bot de WhatsApp de AgendaLlena sin llamar a la Cloud API real de Meta.

# Instrucciones
1. Evaluá las opciones para simular la Cloud API en desarrollo y recomendá una.
2. Hacé configurable la URL base de la Graph API, con la de producción como valor por defecto.
3. Implementá un doble de Meta que conteste los envíos como lo haría la plataforma, y una forma de mandar mensajes entrantes como cliente que pase por el webhook real, con la firma HMAC válida.
4. Asegurá que nada de esto exista en producción.
5. Seguí TDD: cada comportamiento con su test en rojo antes del código.

# Restricciones
- Cambios mínimos en el código de producción: solo lo necesario para que la URL sea configurable.
- La suite existente tiene que seguir en verde.

# Formato de salida
La opción elegida y por qué, los archivos creados, cómo usarlo y los tests.
```

## Supuestos

- "No usar directamente" se interpretó como no depender de Meta en el día a día del desarrollo, sin quitar la integración real.

## Resultado

`GraphApi::base()` configurable con `META_GRAPH_URL`; `MetaFalsaController` que devuelve `wamid` simulados y fallas programables (130429, 131026); `SimuladorDeMeta` que arma y firma los webhooks; un chat en `/dev/whatsapp` y los comandos `dev:whatsapp` y `dev:whatsapp:preparar`. Después se sumó el doble de Google Calendar. El middleware `SoloConSimulador` devuelve 404 en producción. 16 tests en `SimuladorDeWhatsAppTest`. Commit `ebc8b83`.
