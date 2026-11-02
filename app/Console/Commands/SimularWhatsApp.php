<?php

namespace App\Console\Commands;

use App\Dev\SimuladorDeMeta;
use App\Models\Integration;
use Illuminate\Console\Command;

/**
 * `php artisan dev:whatsapp "hola"` · un cliente escribe, sin teléfono ni Meta.
 *
 * Manda el mismo webhook firmado que `/dev/whatsapp`. Las respuestas del bot no
 * se esperan acá —las procesa el worker—: se ven en `/dev/whatsapp` o en el panel.
 */
class SimularWhatsApp extends Command
{
    protected $signature = 'dev:whatsapp
        {texto : Lo que escribe el cliente}
        {--numero= : phone_number_id del negocio (por defecto, el primero con WhatsApp)}
        {--from=5491100000001 : wa_id del cliente}
        {--nombre=Cliente de prueba : Nombre de perfil del cliente}';

    protected $description = 'Simula un mensaje entrante de WhatsApp (solo desarrollo)';

    public function handle(): int
    {
        if (app()->isProduction() || ! config('services.meta.simulador')) {
            $this->error('El simulador está apagado: poné META_SIMULADOR=true en el .env (nunca en producción).');

            return self::FAILURE;
        }

        $numero = $this->option('numero')
            ?? Integration::query()
                ->where('provider', Integration::PROVIDER_META_WHATSAPP)
                ->value('account_identifier');

        if ($numero === null || SimuladorDeMeta::integracionDe($numero) === null) {
            $this->error('Ningún negocio atiende ese número. Corré `php artisan dev:whatsapp:preparar`.');

            return self::FAILURE;
        }

        $respuesta = SimuladorDeMeta::enviarAlWebhook(SimuladorDeMeta::payloadDeTexto(
            $numero, (string) $this->option('from'), (string) $this->option('nombre'), (string) $this->argument('texto'),
        ));

        if (! $respuesta->successful()) {
            $this->error("El webhook contestó HTTP {$respuesta->status()}. ¿Coincide META_APP_SECRET?");

            return self::FAILURE;
        }

        $this->info("Enviado a {$numero} desde {$this->option('from')}. Mirá la respuesta en /dev/whatsapp.");

        return self::SUCCESS;
    }
}
