---
fecha: 2026-11-03
objetivo: Subir en una rama y un PR finales todo lo hecho, con los prompts registrados y la justificación de cada cambio.
estado: aprobado
---

## Pedido original

> necesito subir con todo los prompts nuevos pasados por /meta-prompts y guardados en aprobados y asi como tmb todo lo que hicimos hasta, todo el codigo actualizado y configuraicones  y su justificacion en un nuevo pr llamado finalproject-DIM

## Prompt

```markdown
# Rol
Sos el responsable de la entrega final del proyecto AgendaLlena en el máster AI4Devs.

# Objetivo
Publicar en `diegomottadev/agendallenaia` una rama final con todo lo hecho y abrir su PR con la justificación de cada cambio.

# Instrucciones
1. Pasá por `/meta-prompt` los pedidos posteriores al último registro y guardalos en `.claude/prompts/aprobados/`.
2. Traé al repo de entrega todo el código y la configuración actualizados.
3. Actualizá `readme.md` (URL, infraestructura y despliegue, tests, PRs) y `prompts.md`.
4. Creá la rama siguiendo la consigna, `finalproject-[iniciales]`, y abrí el PR con qué cambia, por qué y su impacto, agrupado por área, con la evidencia de cada verificación.

# Restricciones
- `git push` lo ejecuta el usuario.
- Nada de secretos.
- Lo que no está hecho (por ejemplo, la URL pública activa) se marca como pendiente, no se presenta como hecho.

# Formato de salida
Rama, URL del PR y lista de lo incluido.
```

## Supuestos

- El nombre `finalproject-DIM` se tomó como un error de tipeo de `finalproject-DM`, que es la convención de las ramas anteriores y de la consigna.

## Resultado

Ver el PR de `finalproject-DM`.
