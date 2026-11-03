<?php

namespace App\Console\Commands;

use App\Models\Integration;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Le da un número de WhatsApp y un Google Calendar simulados a cada tenant
 * que no los tiene.
 *
 * Sin integración de Meta la ingesta no sabe a qué negocio pertenece un
 * mensaje y lo descarta, así que los tenants de `RolesDemoSeeder` no se podrían
 * probar desde el simulador. El número es `99` + un hash del id del tenant:
 * no existe en Meta, así que no hay forma de que un envío llegue a alguien real.
 */
class PrepararSimuladorWhatsApp extends Command
{
    protected $signature = 'dev:whatsapp:preparar';

    protected $description = 'Crea integraciones simuladas de WhatsApp y Google Calendar para los tenants sin una (solo desarrollo)';

    public function handle(): int
    {
        if (app()->isProduction() || ! config('services.meta.simulador')) {
            $this->error('El simulador está apagado: poné META_SIMULADOR=true en el .env (nunca en producción).');

            return self::FAILURE;
        }

        foreach (Tenant::query()->orderBy('id')->get() as $tenant) {
            $existente = Integration::query()
                ->where('tenant_id', $tenant->id)
                ->where('provider', Integration::PROVIDER_META_WHATSAPP)
                ->first();

            if ($existente !== null) {
                $this->line("  {$tenant->name}: ya tiene el número {$existente->account_identifier}");

                continue;
            }

            $numero = sprintf('99%013d', crc32((string) $tenant->id));

            Integration::create([
                'tenant_id' => $tenant->id,
                'provider' => Integration::PROVIDER_META_WHATSAPP,
                'account_identifier' => $numero,
                'access_token' => 'token-simulado',
                'settings' => ['verify_token' => 'simulado', 'waba_id' => 'waba-'.$numero],
                'status' => 'connected',
            ]);

            $this->info("  {$tenant->name}: número simulado {$numero}");
        }

        foreach (Tenant::query()->orderBy('id')->get() as $tenant) {
            $this->prepararCalendario($tenant);
        }

        return self::SUCCESS;
    }

    /**
     * Sin calendario el bot corta después del nombre con el fallback, porque no
     * tiene dónde consultar horarios. El token identifica la agenda en el mock
     * de Google (`GoogleFalsoController`), así que cada tenant tiene la suya.
     * `expires_at` vacío: se asume vigente y nunca se intenta renovar contra
     * Google real.
     */
    private function prepararCalendario(Tenant $tenant): void
    {
        $existe = Integration::query()
            ->where('tenant_id', $tenant->id)
            ->where('provider', Integration::PROVIDER_GOOGLE_CALENDAR)
            ->exists();

        if ($existe) {
            $this->line("  {$tenant->name}: ya tiene Google Calendar");

            return;
        }

        Integration::create([
            'tenant_id' => $tenant->id,
            'provider' => Integration::PROVIDER_GOOGLE_CALENDAR,
            'account_identifier' => 'calendario-simulado@'.$tenant->slug.'.test',
            'access_token' => 'google-simulado-'.$tenant->id,
            'refresh_token' => 'google-simulado-refresh',
            'expires_at' => null,
            'status' => 'connected',
        ]);

        $this->info("  {$tenant->name}: Google Calendar simulado");
    }
}
