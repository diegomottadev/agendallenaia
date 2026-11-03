---
description: Revisa si los tests de una clase detectan cambios de comportamiento — mutaciones de dominio propuestas a mano más una corrida de Infection, con los sobrevivientes explicados y el test que falta para cada uno.
argument-hint: "<clase o archivo de app/, ej. app/Meta/NumeroDeWhatsApp.php> [tests que la cubren]"
---

# Rol

Actuá como revisor de calidad de tests de **AgendaLlena** (Laravel 13 + PHPUnit 12, todo PHP en el contenedor). Tu pregunta no es «¿pasan los tests?» sino **«si el código se rompiera de esta forma, ¿algún test se pondría rojo?»**.

# Objetivo

Para `$ARGUMENTS`, encontrar los cambios de comportamiento que la suite **no** detecta y escribir los tests que faltan para detectarlos.

Si `$ARGUMENTS` viene vacío, pedí la clase y frená.

# Pasos

## 1. Mutaciones de comportamiento, a mano

Leé la clase y sus tests (`grep -rl NombreDeLaClase tests/`). Proponé **5 mutaciones de comportamiento relevantes para el negocio**, no sintácticas. En este proyecto suelen ser:

| Mutación | Ejemplo en AgendaLlena |
| :-- | :-- |
| No avanzar | La máquina de estados no transiciona y la conversación queda en el mismo estado |
| Avanzar dos pasos | De `SELECTING_SLOT` directo a `BOOKED` sin pasar por la re-consulta a Google |
| Ignorar el último | La lista de horarios pierde el último de la página; la ventana `t-24h ± 10 min` excluye el borde |
| Aceptar un valor fuera del dominio | Un `wa_id` extranjero tratado como argentino; un horario fuera de la jornada; un estado terminal que cae en `ERROR_FALLBACK` |
| No persistir / no liberar | El candado de `notification_logs` no se escribe; el apartado del horario no se suelta al elegir otro |
| Huso equivocado | Convertir en vez de reinterpretar en la zona del tenant; comparar «hoy» en UTC en vez del día local |

Para cada una: **qué test existente la mataría** (archivo y método). Si ninguno, marcala como **sobreviviente**.

## 2. Infection (si la clase está en `infection.json5`)

Infection es el Stryker de PHP: genera mutantes reales y corre los tests contra cada uno.

```bash
# Base propia siempre: puede haber otra sesión de Claude Code usando agendallena_testing.
docker compose exec -T mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS agendallena_test_mut; GRANT ALL ON agendallena_test_mut.* TO \"agendallena\"@\"%\";"'

# Corrida acotada a las clases de infection.json5 (testsuite Unit, ~15 s)
docker compose exec -T -e DB_DATABASE=agendallena_test_mut app vendor/bin/infection --threads=4 --show-mutations=max

# Solo una clase
docker compose exec -T -e DB_DATABASE=agendallena_test_mut app vendor/bin/infection --threads=4 --filter=app/Meta/NumeroDeWhatsApp.php

# ¿Qué mata un test en particular? (útil para saber si un test nuevo sirve)
docker compose exec -T -e DB_DATABASE=agendallena_test_mut app vendor/bin/infection --threads=4 --min-covered-msi=0 \
  --test-framework-options="--testsuite=Unit --filter=NombreDelTest"
```

Reportes en `build/infection/` (`infection.log`, `summary.log`, `infection.html`; ignorados por git).

- **Si la clase no está en `infection.json5`**, no la agregues a ciegas: Infection corre la testsuite Unit, y una clase cubierta solo por `tests/Feature` (base real) sale con todos sus mutantes «no cubiertos». Para esas, o se agrega `--testsuite=Feature` en una corrida puntual con `--filter` (lento, y **siempre** con base propia) o se queda con el análisis manual del paso 1.
- El piso es `minCoveredMsi: 100` sobre lo cubierto: una corrida que deja un mutante vivo **falla**.

## 3. Clasificar cada sobreviviente

| Tipo | Qué es | Qué hacer |
| :-- | :-- | :-- |
| **Test faltante** | El cambio altera algo observable y ningún test lo mira | Escribir el test (paso 4) |
| **Equivalente** | El código cambia pero el comportamiento no (ej. un `trim` antes de un `preg_replace` que ya borra espacios) | Explicar por qué, y si se confirma, agregarlo a `ignore` en `infection.json5` **por línea** (`Clase::metodo::linea`), con un comentario. Nunca ignorar un mutador entero |
| **Oráculo ambiguo** | Podría ser bug o feature: el AC no dice qué pasa | ⚠️ Marcarlo y preguntar. No escribir un test que fije una decisión que nadie tomó |

## 4. Escribir los tests que faltan

- **Archivo nuevo** en `tests/Unit/` (o `tests/Feature/` si cruza la base). **Nunca edites un test existente** sin aprobación explícita.
- El docblock del test nombra **el mutante que mata** (mutador, archivo y línea) y su **oráculo** (AC, catálogo de flujos, dato calculado a mano). El valor esperado nunca sale de correr el código.
- Un test escrito para matar un mutante **pasa a la primera** contra el código sano: es esperable, y hay que decirlo. Su rojo se demuestra **volviendo a correr Infection** (o la línea `--filter=NombreDelTest` de arriba) y mostrando que el mutante pasó a *killed*.
- No toques `app/`. Si el sobreviviente revela un bug real, reportalo con el contraejemplo; el arreglo va por el ciclo TDD normal.

# Salida

1. Tabla de las 5 mutaciones manuales: mutación · test que la mata · ¿sobrevive?
2. Resultado de Infection: mutantes generados, *killed*, *escaped*, **Covered Code MSI** antes y después.
3. Por cada sobreviviente: tipo (faltante / equivalente / ⚠️ ambiguo) y una línea de por qué.
4. Tests nuevos: archivo, qué mutante mata, oráculo, y la salida de Infection que prueba que ahora muere.
