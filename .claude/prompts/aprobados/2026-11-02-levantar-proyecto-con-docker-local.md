---
fecha: 2026-11-02
objetivo: Levantar AgendaLlena con las imágenes de Docker locales, verificando que sus versiones coincidan con las que pide el proyecto.
estado: aprobado
---

## Pedido original

> estudia el proyecto y que tecnologias usa y levantolo con las imagenes de docker que tenemos disponibles salvo que no concidia su version y lo mismo la db

## Prompt

```markdown
# Rol
Sos un desarrollador senior de Laravel con experiencia en Docker Compose.

# Objetivo
Entender el stack de AgendaLlena y dejar el proyecto corriendo en local con las imágenes de Docker que ya están descargadas en esta máquina.

# Instrucciones
1. Estudiá el proyecto: lenguaje, framework, versiones exigidas (`composer.json`, `package.json`, `Dockerfile`, `docker-compose.yml`), base de datos, caché y colas.
2. Listá las imágenes de Docker disponibles (`docker images`) y comparalas con las versiones que exige el proyecto, incluida la base de datos.
3. Usá una imagen local **solo si su versión coincide**. Si no coincide, decilo y usá la que corresponde.
4. Levantá los servicios, generá la clave, corré las migraciones y compilá los assets siguiendo `CLAUDE.md`.
5. Verificá que la app responda por HTTP y que el worker y el scheduler estén corriendo.

# Restricciones
- Todo PHP se ejecuta dentro del contenedor.
- No modifiques código de aplicación para que arranque: si algo falla, diagnosticalo y reportalo.

# Formato de salida
Tabla del stack con la versión exigida y la imagen usada, los comandos ejecutados, y el estado final de cada servicio.
```

## Supuestos

- "Lo mismo la db" se interpretó como aplicar la misma regla de versión a MySQL y Redis.

## Resultado

Proyecto levantado con Laravel 13 / PHP 8.4 (imagen `sail-8.4/app`), MySQL 8.4 y Redis 7, más los servicios `app`, `worker` y `scheduler`. Se usó un `docker-compose.override.yml` local para las imágenes disponibles. Errores resueltos en el camino: un `\x01` suelto en `.env.example`, `--ansi=0` (reemplazado por `--no-ansi`) y el worker caído antes de migrar.
