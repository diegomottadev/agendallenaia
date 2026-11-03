---
description: Propone de 3 a 5 invariantes de una clase pura y escribe property-based tests con Eris para las más importantes, diciendo qué generadores usó y reportando cualquier contraejemplo sin tocar app/.
argument-hint: "<clase o función, ej. App\\Meta\\NumeroDeWhatsApp o app/Conversacion/TablaDeTransiciones.php>"
---

# Rol

Actuá como especialista en property-based testing sobre **AgendaLlena** (Laravel 13 + PHPUnit 12). La librería es **Eris** (`giorgiosironi/eris`, el fast-check de PHP), ya instalada como dependencia de desarrollo.

# Objetivo

Para `$ARGUMENTS`, encontrar propiedades que valgan **para cualquier entrada** y escribirlas como tests que generan cientos de entradas por corrida. Si `$ARGUMENTS` viene vacío, pedí la clase y frená.

Un test por ejemplos dice «con ESTA entrada sale ESTO». Una propiedad dice «con CUALQUIER entrada de esta forma, vale esto». Sirven para **lo que nadie pensó en escribir**: UTF-8 inválido en un webhook, un número de 12 dígitos justo en el corte, la combinación estado × evento que no está en ningún data provider.

# Pasos

## 1. Proponer de 3 a 5 invariantes

Leé la clase y sus tests existentes. Proponé invariantes con su **oráculo** (de dónde sale que tienen que valer), eligiendo entre estas familias:

| Familia | Ejemplo en AgendaLlena |
| :-- | :-- |
| **Idempotencia** | `NumeroDeWhatsApp::paraGuardar(paraGuardar(x)) === paraGuardar(x)` — la forma canónica no cambia al re-normalizarla |
| **Ida y vuelta / diferencial** | `paraEnviar(paraGuardar(x)) === paraEnviar(x)`; un instante que viaja en el `id` del botón vuelve igual: `ListaDeHorarios::horarioDe('slot_'.$t->getTimestamp()) == $t` |
| **Robustez** | Nunca lanza con cualquier string: `Bienvenida::esPalabraDeReinicio`, el intérprete de respuestas interactivas con un `id` arbitrario |
| **Pertenencia / cierre** | `TablaDeTransiciones::destino` devuelve `null` o un `Estado`; un estado terminal nunca sale a `ERROR_FALLBACK`; `eventosValidosDesde` coincide con `destino` |
| **Invariancia (metamórfica)** | Mayúsculas, espacios y signos alrededor de «menú» no cambian la respuesta; correr la misma conversión en dos husos con el mismo offset hoy no prueba nada (¡cuidado con Santiago y Buenos Aires!) |
| **Orden / monotonía** | Los horarios ofrecidos salen ordenados y dentro de la jornada; ninguno cae en el buffer de un turno existente |

**Si lo invoca un humano, mostrá la lista y esperá su OK antes de escribir.**

## 2. Escribir las dos más importantes (o todas, si son baratas)

En un **archivo nuevo** de `tests/Unit/` (las propiedades corren cientos de veces: nada de base ni HTTP). Nunca edites un test existente.

```php
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

class MisPropiedadesTest extends TestCase
{
    use TestTrait;

    public function test_para_guardar_es_idempotente(): void
    {
        $this->forAll(Generators::string())
            ->then(function (string $x): void {
                $una = NumeroDeWhatsApp::paraGuardar($x);
                $this->assertSame($una, NumeroDeWhatsApp::paraGuardar($una));
            });
    }
}
```

Generadores útiles (`Eris\Generators`):

| Generador | Para qué |
| :-- | :-- |
| `string()` | ASCII imprimible 33–126. **No alcanza solo**: no genera espacios, Unicode ni bytes inválidos |
| `map(fn, seq(choose(0, 255)))` | Bytes arbitrarios, incluido UTF-8 inválido y `\0` — lo que puede traer un webhook manipulado |
| `seq(elements('ñ', '😀', 'مرحبا', "\u{00A0}", …))` | Texto «de celular» |
| `elements(Estado::cases())` × `elements(Transicion::cases())` | Recorrer la matriz estado × evento |
| `tuple(elements('54', '549', '56', '52', …), vector(n, elements('0'…'9')), choose(0, 14))` | Teléfonos con prefijos de la región y largos que cruzan los cortes de 12 y 13 dígitos |
| `choose(a, b)`, `date(...)` | Instantes; combinarlos con husos de offset **distinto hoy** |
| `oneOf(...)` | Mezclar todo lo anterior en una sola propiedad |

Ver `tests/Unit/PropiedadesTest.php` como referencia del proyecto.

## 3. Correr

```bash
docker compose exec -T -e DB_DATABASE=agendallena_test_pbt app php artisan test --filter=MisPropiedadesTest
```

Base propia aunque el test no la use: `artisan test` arranca la app y puede haber otra sesión usando `agendallena_testing`.

- **Si una propiedad falla, no toques `app/`.** Eris achica la entrada hasta el **contraejemplo mínimo** e imprime la semilla. Reportá el contraejemplo, reproducilo con `-e ERIS_SEED=<semilla>`, y decidí con el usuario si es un bug (va por el ciclo TDD normal) o una propiedad mal planteada.
- **Si pasa a la primera** (lo normal sobre código existente), demostrá que no es vacía: corré Infection solo con ese test y mirá cuántos mutantes mata.
  ```bash
  docker compose exec -T -e DB_DATABASE=agendallena_test_pbt app vendor/bin/infection --threads=4 --min-covered-msi=0 \
    --test-framework-options="--testsuite=Unit --filter=MisPropiedadesTest"
  ```
  Una propiedad que no mata ningún mutante no está afirmando nada.

# Salida

1. Las 3–5 invariantes, cada una con su oráculo.
2. Cuáles se escribieron, en qué archivo, y **qué generadores** usó cada una.
3. Resultado de la corrida: iteraciones y aserciones; contraejemplo y semilla si alguna falló.
4. Mutantes que mata cada propiedad (o el conjunto), si se corrió Infection.
