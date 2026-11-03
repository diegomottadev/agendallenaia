---
fecha: 2026-11-03
objetivo: Corregir que el reintento tras un rate limit de Meta deje al cliente sin respuesta.
estado: aprobado
---

## Pedido original

> 1 hazlo y 2 con otro agente ejecutalo   (el punto 1: arreglar el bug del reintento)

## Prompt

```markdown
# Rol
Sos un desarrollador senior de Laravel con experiencia en colas e idempotencia.

# Objetivo
Que, cuando Meta limita un envío (130429) y la cola reintenta el job, el cliente igual reciba la respuesta.

# Contexto
El primer intento aplica la transición de estado y después envía. En el reintento, la conversación ya avanzó y el mismo mensaje cae en un estado sin intérprete: el bot no responde. El test `ReintentoTrasRateLimitTest` y el E2E de caminos de error están en rojo.

# Instrucciones
1. Implementá la opción B: guardar el envío que Meta limitó, asociado al `wamid` entrante, y que el reintento lo mande en vez de reprocesar.
2. Verificá el arreglo rompiéndolo a propósito y corré el E2E tres veces.
3. Documentá el límite del diseño.

# Restricciones
- Sin modificar tests existentes.

# Formato de salida
El arreglo, el rojo y el verde, y la verificación.
```

## Supuestos

- "1 hazlo" se interpretó como aplicar la opción B (reenviar lo pendiente), que era la más precisa de las dos propuestas; la A no deshacía los efectos fuera de la base.

## Resultado

- `MetaRateLimit` lleva el cuerpo del envío; `MetaAdapter::reenviar()`; `ProcessMessageJob` guarda lo pendiente en caché por `wamid`.
- Test de integración verde y verificado rompiendo el arreglo; E2E 3 de 3.
- Límite: los envíos posteriores al limitado dentro de un mismo paso no se recuperan.
- Commit `92d9127`.
