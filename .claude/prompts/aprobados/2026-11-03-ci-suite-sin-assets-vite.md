---
fecha: 2026-11-03
objetivo: Corregir la primera corrida real del CI en GitHub, que dio 85 fallas en la suite.
estado: aprobado
---

## Pedido original

> (log pegado del runner: "Vite manifest not found at: /var/www/html/public/build/manifest.json ... Tests: 85 failed, 598 passed")
>
> me dice en los runners github

## Prompt

```markdown
# Rol
Sos un ingeniero de CI que conoce Laravel, Vite y GitHub Actions.

# Objetivo
Que el workflow `tests` quede en verde en GitHub, partiendo del log del runner.

# Instrucciones
1. Agrupá las fallas por causa con el log completo (`gh run view --log-failed`).
2. Corregí la causa mayoritaria en el workflow, no en los tests.
3. Identificá las fallas restantes y separá las de entorno de las reales.
4. Validá el workflow con actionlint y confirmá con la corrida siguiente.

# Restricciones
- No modifiques tests para que pasen.

# Formato de salida
Causa, arreglo y el resultado de la corrida siguiente.
```

## Supuestos

- La falla no se veía en local porque `public/build` ya existía de un `npm run build` anterior.

## Resultado

- 80 de 85 fallas por un solo motivo: el job de la suite nunca compilaba los assets y las vistas con `@vite` fallaban. Se agregó el build al job.
- Siguiente corrida: 680 OK y 3 fallas, de tests que salían a la red real de Google; se trataron en otro pedido.
