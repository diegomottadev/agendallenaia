---
fecha: 2026-11-03
objetivo: Corregir los tests que fallaban en el CI por salir a la red real de Google.
estado: aprobado
---

## Pedido original

> mejor corrigelos
>
> (luego, para un 4.º test que apareció en el camino: "Sí, corregilo")

## Prompt

```markdown
# Rol
Sos un desarrollador senior de Laravel con TDD estricto.

# Objetivo
Que los 3 tests que fallan en el CI pasen por el motivo correcto, agregándoles los dobles de Google que les faltan, sin cambiar lo que prueban.

# Instrucciones
1. Confirmá la causa de cada falla antes de tocarla.
2. Agregá solo los `Http::fake` que faltan (`freeBusy`, creación de eventos).
3. Si al hacerlo aparece un defecto real, investigalo antes de seguir.
4. Corré la suite completa y los E2E.

# Restricciones
- Tocar tests existentes solo con la aprobación del usuario, y pedirla para cada test nuevo afectado.
- No cambies aserciones salvo con aprobación, explicando por qué.

# Formato de salida
Test por test: la causa y el cambio. Más cualquier defecto encontrado.
```

## Supuestos

- Un 4.º test (`MessageHistoryTest`) también se vio afectado y se corrigió con aprobación aparte.

## Resultado

- **Defecto real encontrado:** el arreglo de la mañana (reenviar lo pendiente) chocaba con `PasoEntregado`. Se revirtió, y la bienvenida pasó a usar `PasoEntregado` como el resto de los pasos.
- 4 tests con los dobles que faltaban.
- CI completo en verde: 683 de 683, 12 de 12 de husos horarios, E2E, `seguridad` e `infra`.
- Commit `b80de09`.
