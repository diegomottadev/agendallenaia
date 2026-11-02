---
fecha: 2026-07-xx
objetivo: Producir el Lean Canvas de AgendaLlena como diagrama mermaid de nueve bloques numerados, con datos concretos de problema, segmentos, propuesta de valor, solución, canales, ingresos, costos, métricas y ventaja injusta.
estado: reconstruido
origen: ingenieria inversa del artefacto (.claude/docs/01-producto/01-lean-canvas.md), no es el prompt original
---

## Advertencia de trazabilidad

⚠️ **Este no es el prompt que se ejecutó.** El Lean Canvas es el primer artefacto de la cadena y se
generó antes de que existiera `/meta-prompt` y la carpeta `prompts/aprobados/`, así que su prompt no
se guardó. Lo que sigue se reconstruyó el 2026-11-02 leyendo el artefacto.

⚠️ **La fecha es una estimación, no un dato.** El repositorio tiene un solo commit para toda la
documentación (2026-11-02) y las fechas de los archivos son las del checkout, así que no hay rastro
de cuándo se generó. Lo único seguro es que es **anterior al 2026-08-05**: ese día el prompt de story
maps ya leía `01-producto/` entero. Se pone `2026-07-xx` porque el día no se puede deducir.
`consigna.md` fija la entrega 1 (producto y arquitectura) para el 12 de junio, lo que sugeriría junio;
no se usó porque las fechas del resto del proyecto no siguen ese calendario.

**Qué es fiable y qué no:**

* **Fiable — verificado contra el archivo:** la forma de salida (mermaid crudo, sin bloque markdown
  alrededor), los nueve bloques con la numeración canónica de Ash Maurya, el reparto en tres filas
  con subgrafos invisibles, el formato de cada caja (título en mayúsculas y negrita, viñetas con `•`).
* **Inferencia:** el *fraseo* de las instrucciones, y sobre todo **de dónde salieron los números**.
  El contexto del prompt de abajo trae las cifras del canvas (ausentismo 20-35 %, VPS USD 20-40,
  setup USD 100-300, MRR USD 80-150). No hay forma de saber si el pedido original las traía o si el
  modelo las propuso: se pusieron como entrada porque un canvas con cifras inventadas por el modelo
  no habría sobrevivido a la revisión de quien lo pidió. Es el supuesto más débil.
* El artefacto **no muestra ediciones posteriores** (no hay anotaciones ⚠️ ni de tickets, a diferencia
  del PRD), así que probablemente es la salida tal cual. Con un solo commit no se puede confirmar.

**Usarlo para regenerar el canvas produciría un artefacto equivalente, no idéntico.**

## Pedido original

No conservado. Del artefacto se deduce un pedido con esta forma:

> armá el lean canvas del producto en mermaid, con los nueve bloques, para PyMEs que agendan por
> WhatsApp

## Prompt

```markdown
# Rol

Actuás como **estratega de producto y emprendedor serial** con experiencia en Lean Startup y en
vender software B2B a PyMEs latinoamericanas, con criterio técnico suficiente para que la caja de
"Solución" nombre tecnología real y no promesas.

# Objetivo

Producir el **Lean Canvas** de un SaaS multi-tenant de agendamiento y cotización por WhatsApp para
PyMEs, como **un único diagrama mermaid** que se pueda pegar en un `.md` y renderice los nueve
bloques del canvas en su disposición clásica.

# Contexto / entrada

Datos del negocio a volcar en el canvas:

| Bloque | Insumo |
| :-- | :-- |
| Problema | Ventas que se pierden por demora en responder; ausentismo alto (20-35 %); rechazo de las PyMEs a CRMs complejos. Alternativa actual: WhatsApp manual y Excel |
| Segmentos | PyMEs latinoamericanas de 3 a 20 empleados: salud y estética, inmobiliarias, distribuidoras B2B, servicios y mantenimiento |
| Propuesta única de valor | Cotizar y agendar el 100 % de las consultas en menos de 15 segundos, sin cambiar de herramientas |
| Solución | Motor en Laravel con webhooks; sincronización OAuth con Google/Outlook; confirmación a t-24 h; volcado a Google Sheets |
| Canales | Outbound tipo *mystery shopper*, cámaras empresariales, referidos |
| Ingresos | Setup único USD 100-300; abono mensual USD 80-150 |
| Costos | VPS en la nube USD 20-40/mes; CAC en cero (se paga en horas de outbound) |
| Métricas clave | Respuesta < 15 s; ausentismo < 8 %; conversión > 35 % |
| Ventaja injusta | Stack senior (Laravel/Vue); cero costo por usuario; venta directa de senior a dueño |

# Instrucciones

1. **Usá la numeración canónica del Lean Canvas** (1 Problema, 2 Segmentos, 3 Propuesta única de
   valor, 4 Solución, 5 Canales, 6 Ingresos, 7 Costos, 8 Métricas, 9 Ventaja injusta) y escribila en
   el título de cada bloque. El número es el orden en que se llena el canvas, no el de lectura.
2. **Disposición en tres filas**, de arriba hacia abajo:
   * Fila 1: Problema · Solución · Propuesta única de valor · Ventaja injusta · Segmentos.
   * Fila 2: Métricas clave · Canales.
   * Fila 3: Estructura de costos · Flujo de ingresos.
3. **Cada bloque es un nodo** con el título en negrita y mayúsculas (`<b>1. PROBLEMA</b>`), un salto
   de línea y los ítems como viñetas `•` separadas por `<br>`. La propuesta de valor va como una frase
   entre comillas simples, no como viñetas. Las alternativas actuales van dentro de Problema, bajo un
   subtítulo en negrita.
4. **Cifras concretas siempre.** Si un ítem admite número (porcentaje, USD, segundos), va con número.
   Un canvas sin cifras no se puede invalidar.
5. Ítems cortos: una línea cada uno, sin oraciones completas.

# Restricciones

* Solo el diagrama: **mermaid crudo, sin bloque ```` ``` ```` alrededor y sin texto antes ni
  después.** El archivo se renderiza como mermaid directo.
* `flowchart TB` con un `subgraph` por fila (`direction LR`) y los subgrafos **invisibles**
  (`style RowN fill:none,stroke:none`) — las filas ordenan, no se dibujan.
* Sin flechas entre bloques: el canvas no es un flujo.
* Español rioplatense en el contenido; los términos técnicos (OAuth, webhooks, MRR, CAC) quedan en
  inglés.

# Formato de salida

Un único archivo `.claude/docs/01-producto/01-lean-canvas.md` con el `flowchart` completo.
```

## Supuestos de la reconstrucción

1. **Las cifras venían en el pedido.** Es el supuesto más fuerte: si las propuso el modelo, el
   contexto de arriba está de más y el prompt original era bastante más corto.
2. **Los subgrafos invisibles fueron instrucción.** Tres filas con `fill:none,stroke:none` es una
   decisión de maquetación demasiado específica para emerger sola.
3. **"Google/Outlook" en Solución es del pedido, no del modelo.** Outlook quedó fuera del MVP recién
   con T-007 (2026-08-19) y el canvas no se corrigió: es la huella de que se generó antes de esa
   decisión.

## Resultado

Ejecutado antes del 2026-08-05 (con el prompt original, no con este). Artefacto:
`.claude/docs/01-producto/01-lean-canvas.md`, un `flowchart TB` de nueve nodos en tres filas.

Es la fuente de la que bajan el PRD (stack, precio, KPIs de 15 s y ausentismo) y el contexto de
producto que repiten todos los prompts posteriores.
