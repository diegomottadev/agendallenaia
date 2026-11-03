---
fecha: 2026-11-03
objetivo: Aplicar los arreglos de seguridad necesarios para que el MVP cumpla el OWASP Top 10:2025, según la evidencia del análisis.
estado: aprobado
---

## Pedido original

> Eres experto en ciberseguridad, en base a la evidencia, ejecuta los arreglos necesarios para que cumpla en la medida de este mvp el framework OWASP

## Prompt

```markdown
# Rol
Sos un experto en ciberseguridad (AppSec y DevSecOps) que trabaja con TDD en Laravel.

# Objetivo
Ejecutar los arreglos necesarios para que AgendaLlena cumpla el OWASP Top 10:2025 **en la medida de un MVP**, a partir de la evidencia del análisis previo.

# Instrucciones
1. Por cada brecha con evidencia, aplicá el control proporcionado al MVP:
   - **A02:** cabeceras de seguridad con una CSP sin scripts en línea;
   - **A03:** auditoría de dependencias e imagen, más las actualizaciones necesarias;
   - **A05:** test de XSS;
   - **A06:** límites del webhook público.
2. Código con TDD: test en rojo antes. Si el test cubre algo que ya existía, verificalo rompiendo el código a propósito.
3. Corré las herramientas de verdad (`composer audit`, `npm audit`, gitleaks, Trivy) y corregí lo que encuentren.
4. Verificá que la CSP no rompa el panel ni el simulador, y hacé una prueba de humo de la imagen de producción.
5. Dejá las reglas en `CLAUDE.md` y los controles automatizados en el CI.

# Restricciones
- No sobredimensiones: lo que no aplica al MVP (por ejemplo segundo factor sin alta de usuarios) queda documentado como pendiente.
- Las imágenes y acciones de terceros, fijadas por versión.

# Formato de salida
Tabla por categoría con el arreglo y la evidencia, más lo pendiente.
```

## Supuestos

- "En la medida de este MVP" se interpretó como no agregar funcionalidad nueva (alta de usuarios, segundo factor, alertas) y documentarla como pendiente.

## Resultado

- `CabecerasDeSeguridad`: CSP con nonce y el resto de las cabeceras.
- `LimitarCuerpoDelWebhook` y `throttle:webhook-meta`.
- `SeguridadOwaspTest`: 7 tests.
- Dependencias sin vulnerabilidades (laravel/framework crítica, shell-quote crítica, entre otras).
- Imagen FrankenPHP 1.13.4 + `apt upgrade`: Trivy de 488 a 0 altas o críticas con arreglo.
- Workflow `seguridad`, Dependabot y sección de seguridad en `CLAUDE.md`.
- Commit `b37f20d`.
