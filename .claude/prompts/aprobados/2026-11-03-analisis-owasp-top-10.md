---
fecha: 2026-11-03
objetivo: Analizar con evidencia del código qué del OWASP Top 10 aplica a AgendaLlena, antes de cambiar nada.
estado: aprobado
---

## Pedido original

> n'/home/diegomotta/Documentos/IA Assitend Dev/modulo-15-devsecops-monitorizacion-observabilidad-seguridad/prompts' revisa si hay algo de la oswap para poder aplicar en este proyecto, primero analiza

## Prompt

```markdown
# Rol
Sos un experto en ciberseguridad de aplicaciones web y conocés el OWASP Top 10:2025.

# Objetivo
Revisar los prompts del módulo 15 y determinar qué de OWASP se puede aplicar a AgendaLlena. **Solo análisis**: no se cambia nada.

# Contexto
Prompts del curso: `/home/diegomotta/Documentos/IA Assitend Dev/modulo-15-devsecops-monitorizacion-observabilidad-seguridad/prompts`.

# Instrucciones
1. Identificá en los prompts lo que es de OWASP y decidí si aplica o no a este proyecto (Terraform y LLM, por ejemplo, no aplican).
2. Contrastá el código con cada categoría del Top 10:2025, con evidencia concreta: archivo, búsqueda o configuración.
3. Clasificá cada categoría como bien, parcial o faltante.
4. Proponé los arreglos por prioridad.

# Restricciones
- No modifiques archivos.
- Nada de afirmaciones sin evidencia.

# Formato de salida
Tabla de piezas del módulo que aplican, tabla de categorías OWASP con estado y evidencia, y recomendaciones priorizadas.
```

## Supuestos

- Se usó el OWASP Top 10:2025, la versión que cita el módulo.

## Resultado

Análisis entregado sin cambios:
- **Bien:** A01, A04, A05 y A08.
- **Parciales:** A02 (faltaban cabeceras), A06 (webhook sin límites), A07, A09 y A10.
- **Faltante:** A03 (sin auditoría de dependencias en el CI).
