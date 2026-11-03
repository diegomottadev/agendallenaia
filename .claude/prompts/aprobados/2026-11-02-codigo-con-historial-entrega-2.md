---
fecha: 2026-11-02
objetivo: Llevar el código del proyecto al repo de entrega, con todo su historial de git, para la entrega 2.
estado: aprobado
---

## Pedido original

> dale armemos lo que falta
>
> si trae con el histori de agenda llena
>
> (antes: "no hagas caso a las fechas eso no importa")

## Prompt

```markdown
# Rol
Sos un desarrollador senior que maneja git con fluidez.

# Objetivo
Armar la entrega 2 (código funcional) en `agendallenaia`, trayendo el código desde `agendallena` **con todo su historial**.

# Instrucciones
1. Creá la rama `feature-entrega2-DM` en `agendallenaia`.
2. Mergeá la rama de trabajo de `agendallena` con `--allow-unrelated-histories` para conservar cada commit.
3. Resolvé los conflictos con la plantilla de entrega, por ejemplo `README.md` contra `readme.md`, sin perder contenido.
4. Ajustá lo que dependía del repo de origen, como el nombre de la imagen de Docker en el despliegue.
5. Actualizá el readme para que el código se entienda desde este repo.

# Restricciones
- No tengas en cuenta las fechas de la consigna.
- `git push` lo ejecuta el usuario.

# Formato de salida
Commits creados, conflictos resueltos y estado de la rama.
```

## Supuestos

- "Lo que falta" se refiere al hito siguiente de la consigna, la entrega 2.

## Resultado

- Rama `feature-entrega2-DM` con 11 commits de historia mergeados (`14999f5`).
- `README.md` se movió a `docs/README-codigo.md`.
- Imagen parametrizada con `IMAGE_NAME` (`149d3e3`).
- El CI no corrió por un bloqueo de facturación de la cuenta de GitHub.
