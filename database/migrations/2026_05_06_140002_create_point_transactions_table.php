<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('ticket_id')->nullable()->index();
            $table->unsignedBigInteger('redemption_id')->nullable()->index();

            // tipo de movimiento
            $table->enum('tipo', ['ganado', 'canjeado', 'expirado', 'ajuste_admin']);

            // signed: positivo para ganado/ajuste positivo; negativo para canjeado/expirado/ajuste negativo
            $table->integer('puntos');
            $table->integer('balance_despues');

            // snapshots de reglas vigentes al momento (protegen al usuario ante cambios futuros)
            $table->decimal('multiplicador_aplicado', 6, 3)->nullable();
            $table->decimal('earning_rate_snapshot', 10, 2)->nullable();
            $table->decimal('point_value_snapshot', 10, 4)->nullable();

            $table->string('descripcion', 255);
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
    }
};
