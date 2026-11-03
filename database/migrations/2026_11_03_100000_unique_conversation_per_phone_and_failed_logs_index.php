<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dos índices que faltaban, revisados contra el esquema real para la entrega 2.
 *
 * - `conversations (tenant_id, user_phone)` pasa de índice a **único**. La
 *   ingesta resuelve con `firstOrCreate`, que no es atómico: dos webhooks
 *   simultáneos del mismo teléfono podían crear dos conversaciones. Ahora la
 *   garantía es la base, igual que con `messages.whatsapp_message_id`.
 *   ⚠️ Si una base ya tiene duplicados, esta migración falla: hay que fusionarlos antes.
 *   Primero se crea el único y después se borra el índice viejo, porque la FK
 *   de `tenant_id` necesita siempre un índice que empiece por esa columna.
 *
 * - `notification_logs (tenant_id, status)`: el panel de recordatorios fallidos
 *   filtra por esas dos columnas. Hasta ahora solo existía el índice de la FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->unique(['tenant_id', 'user_phone']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'user_phone']);
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        // MySQL descartó solo el índice implícito de la FK al aparecer el
        // compuesto: hay que devolverle uno antes de borrar el compuesto.
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->index('tenant_id');
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->index(['tenant_id', 'user_phone']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'user_phone']);
        });
    }
};
