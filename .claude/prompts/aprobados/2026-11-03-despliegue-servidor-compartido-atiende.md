---
fecha: 2026-11-03
objetivo: Desplegar AgendaLlena en agendallenaia.atiende.lat sobre el droplet existente de 512 MB, sin contratar otro.
estado: aprobado
---

## Pedido original

> ok para subir a digital primero tengo que crear el dominio y https similara esto [config de nginx de avisos.atiende.lat] pero que se llame agendallenaia.atiende.lat cargalo en digital ocoen
>
> (seguido de: "no quiero usar un droplet nuevo me alcanza la guita", "bueno podemos dar de baja un proyecto por las imagenes", "que mas podemops liberas de gitial onceon" y salidas de diagnóstico del servidor)

## Prompt

```markdown
# Rol
Sos un especialista DevSecOps que trabaja sobre infraestructura existente y con presupuesto limitado.

# Objetivo
Publicar AgendaLlena en `agendallenaia.atiende.lat` con HTTPS, igual que `avisos.atiende.lat` (nginx del host con el certificado comodín), en el droplet actual de 512 MB y sin contratar uno nuevo.

# Instrucciones
1. Investigá primero: DNS de `atiende.lat`, contenedores, memoria, disco y red de MySQL en el servidor, con comandos de solo lectura que corra el usuario.
2. Liberá recursos sin romper los proyectos existentes, verificando qué atiende cada uno antes de proponer darlo de baja.
3. Achicá la imagen de producción y la RAM por proceso, midiendo y no estimando.
4. Escribí el compose del modo compartido, el vhost de nginx, el SQL de la base y el `.env` de ejemplo.
5. Hacé que Laravel confíe en el proxy (TDD), sin abrir la puerta a cabeceras falsas.
6. Simulá el servidor en local de punta a punta antes de entregar.

# Restricciones
- No tocar `atiende`, `pedidos` ni `avisos`, ni sus bases.
- Base y usuario propios con mínimo privilegio, en una red aislada.
- Secretos generados en el servidor, nunca en el chat.

# Formato de salida
Diagnóstico, plan, archivos, verificaciones y los comandos para el servidor.
```

## Supuestos

- El DNS de `atiende.lat` está en Spaceship, no en DigitalOcean: el comodín ya apuntaba al servidor y no hubo que crear registros.
- No se dio de baja ningún proyecto: `pedidos_platform` resultó ser `admin.atiende.lat`.

## Resultado

- Imagen Alpine: de 914 a 387 MB. Unos 110 MB de RAM entre los cuatro contenedores.
- `docker-compose.compartido.yml` usa el `mysql8` existente por una red propia (`agendallena_mysql`), para no entrar en la de `atiende`, que tiene PHP-FPM sin autenticación.
- `config/trustedproxy.php` con `ProxyDeConfianzaTest`; vhost de nginx; deploy en dos modos; guía en `docs/despliegue-compartido.md`; logs rotados.
- Simulación local completa en verde.
- **Error durante la simulación, ya revertido:** se reemplazaron contenedores del entorno de desarrollo por usar el mismo nombre de proyecto. Se restauraron y se verificaron con E2E.
- Commits `c4e141c` y el de la rotación de logs.
