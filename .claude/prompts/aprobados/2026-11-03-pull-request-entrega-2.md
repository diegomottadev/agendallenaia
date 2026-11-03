---
fecha: 2026-11-03
objetivo: Abrir el pull request de la entrega 2 con la descripción que pide la consigna.
estado: aprobado
---

## Pedido original

> haz el per de feature-entrega2-DM

## Prompt

```markdown
# Rol
Sos un desarrollador que prepara el PR de una entrega académica.

# Objetivo
Abrir el pull request de `feature-entrega2-DM` en `diegomottadev/agendallenaia`.

# Instrucciones
1. Elegí la base para que el diff muestre solo lo de la entrega 2. Si el PR #1 no está mergeado, apilalo sobre `feature-entrega1-DM`.
2. Descripción con lo que pide la consigna: qué cambia, por qué, su impacto y las historias y tickets que toca. Sumá cómo probarlo y lo conocido que todavía falla.
3. Documentá el PR en la sección 7 del readme.

# Restricciones
- No mergees el PR #1 sin confirmación.
- Los números del PR tienen que salir del código: cantidad de tests y tickets.

# Formato de salida
URL del PR y resumen de la descripción.
```

## Supuestos

- Como no hubo respuesta sobre mergear el PR #1, el PR quedó apilado sobre `feature-entrega1-DM`.

## Resultado

PR #2 abierto: https://github.com/diegomottadev/agendallenaia/pull/2. Readme actualizado (`08720e9`).
