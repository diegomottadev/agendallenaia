<?php

namespace App\Dev;

/**
 * La regla única de cuándo existe el simulador de WhatsApp: la usan
 * `SoloConSimulador` y los comandos `dev:whatsapp` y `dev:whatsapp:preparar`.
 *
 * Hace falta `META_SIMULADOR=true`, y en producción además un permiso
 * explícito, `META_SIMULADOR_EN_PRODUCCION=true`, pensado solo para un servidor
 * de demostración. ⚠️ Con el permiso encendido, `/dev/whatsapp` firma webhooks
 * con `META_APP_SECRET` para cualquiera que llegue a la URL: nunca junto con el
 * secreto de una app de Meta real.
 */
final class SimuladorHabilitado
{
    public static function ahora(): bool
    {
        if (! config('services.meta.simulador')) {
            return false;
        }

        return ! app()->isProduction() || config('services.meta.simulador_en_produccion');
    }
}
