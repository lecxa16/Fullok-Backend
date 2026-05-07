<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();

            $table->string('folio', 60);
            $table->decimal('monto', 10, 2);
            $table->decimal('litros', 8, 3);
            $table->enum('tipo_combustible', ['magna', 'premium', 'diesel']);
            $table->date('fecha_ticket');

            $table->string('foto_path', 500)->nullable();

            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado'])
                ->default('pendiente')
                ->index();
            $table->text('motivo_rechazo')->nullable();

            $table->foreignId('revisado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('revisado_at')->nullable();

            // Snapshots calculados al aprobar (para auditoría)
            $table->unsignedInteger('puntos_acreditados')->nullable();
            $table->decimal('multiplicador_aplicado', 6, 3)->nullable();
            $table->decimal('earning_rate_snapshot', 10, 2)->nullable();
            $table->string('tier_snapshot', 20)->nullable();

            $table->timestamps();

            // Evita duplicados: mismo folio en misma estación misma fecha
            $table->unique(['station_id', 'folio', 'fecha_ticket']);
            $table->index(['user_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
