# C4 nivel 3 · Componentes del backend Laravel

> **Actualizado el 2026-11-03 contra el código.** La versión anterior dibujaba piezas que nunca se construyeron:
> - un *Quote Calculator Engine*, cuando el cotizador quedó diferido (T-016, T-040, T-041);
> - un *Human Takeover Detector* que leía pausas en Redis, cuando con el modelo de dos números (RF-A4) la pausa se activa **desde el panel** y vive en la base;
> - un webhook de *Sync Inversa Watch* de Google, cuando el watch quedó diferido (T-027, T-028, decisión § 9) y lo reemplaza la conciliación cada 15 minutos.
>
> Lo diferido aparece abajo con línea punteada y marcado con ⚠️, para que no se lea como existente.

```mermaid
flowchart TB
    subgraph LaravelApp["Contenedor: aplicación Laravel (web · worker · scheduler)"]

        subgraph HttpLayer["1 · HTTP"]
            WebhookCtrl["WhatsAppWebhookController<br><i>handshake + ingesta rápida</i>"]
            Guards["VerifyMetaSignature · LimitarCuerpoDelWebhook<br>throttle:webhook-meta · CabecerasDeSeguridad"]
            Panel["Panel (Blade + Vue)<br><i>conversaciones, asistencia, fallidos,<br>turnos en duda, configuración</i>"]
            OAuth["GoogleOAuthController<br><i>state firmado</i>"]
        end

        subgraph AppLayer["2 · Aplicación y colas"]
            Job["ProcessMessageJob<br><i>procesamiento asíncrono e idempotente</i>"]
            Paso["PasoEntregado<br><i>el estado avanza solo si el mensaje salió</i>"]
            Pausa["Pausa · Derivacion<br><i>pausa desde el panel (60 min) y derivación a persona</i>"]
            Sched["Tareas agendadas<br><i>conversaciones:expirar · recordatorios:enviar<br>agendamientos:conciliar · plantillas:sincronizar</i>"]
        end

        subgraph DomainCore["3 · Dominio"]
            FSM["MaquinaDeEstados + TablaDeTransiciones<br><i>10 estados, versión optimista</i>"]
            Reserva["Reserva · ReservaTemporal<br><i>apartado con TTL + re-consulta antes de crear</i>"]
            Recordatorio["RespuestaAlRecordatorio<br><i>Confirmar · Re-agendar · Cancelar</i>"]
            Quote["⚠️ Cotizador<br><i>diferido: T-016, T-040, T-041</i>"]
        end

        subgraph InfraAdapters["4 · Adaptadores"]
            MetaAdapter["MetaAdapter<br><i>texto, interactivos, plantillas</i>"]
            Calendar["CalendarioDeGoogle · GoogleConnection<br><i>freeBusy, eventos, renovación de token</i>"]
            Sheets["PlanillaDeGoogle<br><i>volcado de leads</i>"]
            Eloquent["Modelos Eloquent + BelongsToTenant<br><i>aislamiento por tenant_id</i>"]
        end
    end

    subgraph Ext["Externos y datos"]
        MetaAPI["Meta WhatsApp Cloud API"]
        GCal["Google Calendar API"]
        GSheets["Google Sheets API"]
        MySQL[("MySQL 8.4")]
        Redis[("Redis 7<br>locks y apartados")]
    end

    MetaAPI -->|webhook firmado| Guards --> WebhookCtrl
    WebhookCtrl -->|encola| MySQL
    MySQL -->|job| Job
    Job --> Pausa
    Job --> FSM
    FSM --> Paso --> MetaAdapter
    FSM --> Reserva --> Calendar
    Job --> Recordatorio
    Job --> Sheets
    Sched --> MetaAdapter
    Sched --> Calendar
    Panel --> Pausa
    Panel --> Eloquent
    OAuth --> Calendar
    Reserva --> Redis
    Eloquent --> MySQL
    MetaAdapter --> MetaAPI
    Calendar --> GCal
    Sheets --> GSheets
    FSM -.->|⚠️ diferido| Quote
    GCal -.->|⚠️ watch diferido T-027/T-028:<br>lo cubre agendamientos:conciliar| Sched
```

## Componentes

| Componente | Responsabilidad | Dónde |
| :-- | :-- | :-- |
| **Guardas del webhook** | Límite de pedidos y de tamaño, y firma HMAC sobre el cuerpo crudo, **antes** de tocar código de aplicación (OWASP A06/A08) | `app/Http/Middleware/` |
| **ProcessMessageJob** | Resuelve la conversación, deduplica por `wamid`, persiste el mensaje y lo entrega al flujo | `app/Jobs/` |
| **PasoEntregado** | Si el mensaje del paso no se entrega, deshace la transición para que el reintento recorra el mismo camino (AC-09.4) | `app/Conversacion/` |
| **MaquinaDeEstados** | Transiciones declaradas en una tabla; versión optimista contra botones viejos | `app/Conversacion/` |
| **Reserva / ReservaTemporal** | Apartado del horario en Redis (TTL 5 min) y re-consulta a Google antes de crear el evento (T-029) | `app/Conversacion/` |
| **Pausa / Derivacion** | El operador pausa el bot desde el panel; el bot deriva a una persona tras intentos fallidos | `app/Conversacion/` |
| **Tareas agendadas** | Expirar conversaciones, recordatorio t-24h, conciliar con Google y sincronizar plantillas | `routes/console.php` |
| **Adaptadores** | Únicos puntos que hablan con Meta y Google; URL base configurable para el simulador | `app/Meta/`, `app/Services/Google/` |

⚠️ **Lo que el diagrama no tiene y figuraba antes:** el cotizador, el watch de Google y el aviso t-2h. Los tres están diferidos o recortados; ver `tickets/README.md`.
