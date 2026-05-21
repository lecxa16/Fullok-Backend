<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quita el unique constraint (station_id, folio, fecha_ticket) para que
 * el admin pueda toggle la validación de folios duplicados via
 * `tickets_allow_duplicate_folios`. La validación queda solo a nivel
 * código en TicketController::store, condicionada por el setting.
 */
return new class extends Migration {
    public function up(): void
    {
        // MySQL usaba el unique compuesto como índice de respaldo de la FK
        // station_id. Antes de dropearlo, creamos un índice simple sobre
        // station_id para que la FK siga teniendo soporte (error 1553).
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('station_id', 'tickets_station_id_index');
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['station_id', 'folio', 'fecha_ticket']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unique(['station_id', 'folio', 'fecha_ticket']);
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_station_id_index');
        });
    }
};
