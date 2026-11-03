---
fecha: 2026-11-03
objetivo: Recomendar el aprovisionamiento en DigitalOcean y el CI/CD con GitHub, y escribir la infraestructura en Terraform, siguiendo los prompts del módulo 13.
estado: aprobado
---

## Pedido original

> luego de esto necesito que actues como un especialista SecDevOp, me gustaria que analizaras este proyecto y me recomiendo la mejor forma de aprovisionar mi servidor en digital y hacer el CI  - CD con githubs y que me dieras el codigo de arquitectura para implementar terraform '/home/diegomotta/Documentos/IA Assitend Dev/modulo-13-devsecops-infraestructura-con-ia/prompts/01-infraestructura-como-codigo-con-ia.md' '/home/diegomotta/Documentos/IA Assitend Dev/modulo-13-devsecops-infraestructura-con-ia/prompts/02-ci-cd-y-despliegue.md' guiate por estos prompots por fa para est eproyecto
>
> (y a mitad de camino: "como maneja si quiero hacer las migraciones de laravel en el servidor como lo hace por cada cambio que subo?")

## Prompt

```markdown
# Rol
Sos un especialista DevSecOps con experiencia en DigitalOcean, Terraform y GitHub Actions.

# Objetivo
Analizar AgendaLlena, recomendar la mejor forma de aprovisionar su servidor en DigitalOcean y de hacer CI/CD con GitHub, y entregar el código de Terraform. Guiate por los prompts del módulo 13:
- diagrama → Terraform;
- iterar hasta pasar `validate` y `plan`;
- revisar la seguridad listando cada cambio;
- CI/CD con gestión de errores.

# Contexto
Laravel 13 / PHP 8.4, MySQL 8.4, Redis 7, worker y scheduler; imagen FrankenPHP; workflows `tests` y `seguridad` existentes; `docs/despliegue.md`.

# Instrucciones
1. Recomendá la arquitectura con sus decisiones justificadas: tamaño, región, persistencia, red, acceso SSH y alertas.
2. Escribí el Terraform separado en `versions`, `variables`, `main`, `monitoring` y `outputs`, con estado remoto y un cloud-init endurecido.
3. Reescribí el CD: verificación previa, escaneo de la imagen, aprobación, mínimo privilegio y rollback.
4. Sumá un workflow de IaC: validación en cada PR, y `plan` y `apply` manuales.
5. Validá todo con `terraform validate`, tflint, Trivy config, el esquema de cloud-init, actionlint y shellcheck.
6. Explicá cómo corren las migraciones en cada deploy y sus reglas de compatibilidad.

# Restricciones
- No apliques nada contra la cuenta real.
- Sin secretos en el repo.
- Los riesgos que se acepten, documentados en el código.

# Formato de salida
Recomendación, archivos, verificaciones y respuesta sobre migraciones.
```

## Supuestos

- No hay región de DigitalOcean en Latinoamérica: se eligió `nyc3` por latencia.

## Resultado

- `infra/terraform`: droplet, Volume con `prevent_destroy`, IP reservada, VPC, Cloud Firewall (SSH solo admin, egress por puerto), alertas y uptime, Spaces.
- `cloud-init` endurecido; `deploy.yml` reescrito (SSH abierto solo para la IP del runner, huella fijada, token por stdin, rollback); `infra.yml` (apply del plan aprobado).
- **Autorrevisión:** se corrigieron un token visible en `ps` y un `apply` que se aprobaba sin haber visto el plan.
- Todo verificado con las herramientas del paso 5. Las migraciones corren solas en cada deploy, con dump previo.
- Commit `356d8c2`.
