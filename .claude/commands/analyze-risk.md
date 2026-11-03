---
description: Analiza el diff de la rama contra main y asigna un risk score de 1 a 5 por archivo, con qué puede romper, qué tests lo cubren y qué tests faltan.
argument-hint: "[rama o rango base, por defecto main...HEAD] [carpeta a acotar, ej. app/]"
---

# Rol

Actuá como tech lead revisando una rama de **AgendaLlena** antes del merge: un middleware de agendamiento por WhatsApp (Laravel 13 + MySQL + Redis, Meta Cloud API, Google Calendar) donde los defectos caros son **silenciosos** — el turno sale corrido una hora, el recordatorio no sale, el bot no contesta — y la suite queda verde igual.

# Objetivo

Para cada archivo tocado: qué lógica de negocio cambia, qué puede romper, qué tests lo cubren hoy, un **risk score de 1 a 5** y los tests que faltan.

**No cambies código ni tests.** El entregable es el análisis.

# Entrada

Base del diff: `$ARGUMENTS` (si viene vacío, `main...HEAD`). Incluí también lo no commiteado:

```bash
git diff --stat main...HEAD
git diff main...HEAD -- app/ routes/ config/ database/ resources/views/
git status --porcelain            # archivos nuevos sin trackear también cuentan
```

⚠️ Si hay **otra sesión de Claude Code** abierta en el proyecto, el árbol de trabajo puede tener cambios ajenos. Si ves archivos que la rama no explica, decilo en vez de analizarlos como propios.

# Cómo puntuar

| Score | Criterio en este proyecto |
| :-: | :-- |
| **5** | Toca una invariante que se viola en silencio: **aislamiento por `tenant_id`** (RNF-01), **UTC en base** (RNF-02), idempotencia de `bookings` o del `wamid`, firma HMAC del webhook, tokens cifrados en `integrations`. O cambia `routes/console.php` (cadencia de las tareas agendadas: la ventana `t-24h ± 10 min` depende de correr cada 10) |
| **4** | Máquina de estados (`TablaDeTransiciones`, `MaquinaDeEstados`, `ProcessMessageJob`), apartado del horario en Redis, envío a Meta (`MetaAdapter`, plantillas, ventana de 24 h), conversión de husos, normalización de teléfonos (el 9 argentino) |
| **3** | Lógica de dominio pura con tests unitarios (listas interactivas, textos del bot), migraciones aditivas, controladores del panel |
| **2** | Vistas Blade, estilos, comandos de mantenimiento sin efecto sobre turnos |
| **1** | Documentación, comentarios, tests solos, config de desarrollo |

Subí un punto si el archivo **no tiene ningún test que lo ejercite** o si agrega código que **nadie llama en producción** (este proyecto tuvo nueve casos de «suite verde, circuito muerto»: buscá los llamadores con `grep` antes de puntuar). Bajá uno si hay un test de integración que recorre el cambio de punta a punta.

# Pasos

1. Listá los archivos del diff y agrupalos por capa (`app/`, `routes/`, `database/`, `resources/`, `tests/`, `e2e/`).
2. Para cada archivo de código: leé el diff, resumí en una línea **qué regla de negocio cambia** y en otra **qué se rompe si está mal**, en términos del cliente o de la PyME («el recordatorio sale a un número extranjero mal formado», no «el método devuelve otra cosa»).
3. Buscá los tests que lo cubren: `grep -rln "NombreDeLaClase\|nombre-de-ruta" tests/ e2e/`. Nombrá archivo y método, no solo «hay tests».
4. Si podés, corré **solo** esos tests con base propia para confirmar que están verdes:
   ```bash
   docker compose exec -T -e DB_DATABASE=agendallena_test_riesgo app php artisan test --filter=NombreDelTest
   ```
   No corras la suite completa contra `agendallena_testing`.
5. Para los archivos con score ≥ 4, proponé los tests que faltan, priorizando los **casos borde del dominio**: husos (cambio estacional, día local vs UTC, turnos que cruzan medianoche), el 9 de los móviles argentinos, horarios en el borde de la jornada, la ventana de 24 h de Meta, dos tenants, el mismo `wamid` dos veces.
6. Si el archivo es una clase pura listada en `infection.json5`, sugerí correr `/mutation-review` sobre ella.

# Salida

```markdown
| Archivo | Qué cambia | Qué puede romper | Tests que lo cubren | Riesgo | Por qué (una línea) |
| :-- | :-- | :-- | :-- | :-: | :-- |
```

Ordenada de mayor a menor riesgo. Debajo:

- **Tests que faltan**, numerados, cada uno con: nivel (unitario / integración / E2E), nombre propuesto, oráculo (AC, catálogo, valor calculado a mano) y el archivo que protege.
- **⚠️ Código sin llamador en producción**, si apareció.
- **Score global de la rama**: el máximo de los archivos, no el promedio.
