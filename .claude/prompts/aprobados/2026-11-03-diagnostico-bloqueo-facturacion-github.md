---
fecha: 2026-11-03
objetivo: Diagnosticar por qué GitHub Actions no arrancaba los jobs y dejar evidencia en el PR mientras se resolvía.
estado: aprobado
---

## Pedido original

> ok cual es el problema de fa turaicon de github como lo arregloo?
>
> (seguido de: "lo puedes cancelar pasarlo a freee", una captura de Billing → Licensing, "no hay nada", y "si" para comentar en el PR)

## Prompt

```markdown
# Rol
Sos un ingeniero de plataforma que conoce la facturación de GitHub y GitHub Actions.

# Objetivo
Explicar por qué los workflows fallan con *"The job was not started because your account is locked due to a billing issue"* y cómo resolverlo.

# Instrucciones
1. Diagnosticá con evidencia: estado de los runs (`gh run view`) y uso facturable de la cuenta (`gh api /users/<user>/settings/billing/usage`, con el scope `user`).
2. Si te piden pasar la cuenta a Free, verificá primero el plan actual.
3. Descartá causas en orden: suscripciones pagas, consumo por encima de la cuota gratuita, facturas impagas.
4. Si no queda causa del lado del usuario, redactá el ticket para el soporte de GitHub.
5. Si el usuario lo aprueba, dejá un comentario en el PR que explique el CI en rojo, con la evidencia de las corridas locales.

# Restricciones
- No ingreses credenciales ni cambies la configuración de facturación: eso lo hace el usuario en la web.
- Comentar en el PR solo con aprobación explícita.

# Formato de salida
La causa, la evidencia, los pasos para resolverlo y, si corresponde, el texto del ticket.
```

## Supuestos

- "Pasarlo a Free" se resolvió verificando el plan: ya estaba en Free y Copilot Free, así que no había nada que cancelar.
- El Chrome automatizado no tenía sesión en GitHub, y no se ingresan contraseñas en nombre del usuario.

## Resultado

- Diagnóstico: plan Free, consumo de 2026 de USD 0 en todos los meses, sin facturas → el bloqueo solo lo levanta el soporte. Se redactó el ticket.
- Comentario en el PR #2 con la evidencia local.
- Cuando GitHub levantó el bloqueo, el comentario se editó para mostrar el CI en verde.
