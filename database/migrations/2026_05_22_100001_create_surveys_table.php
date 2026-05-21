<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            // Tipo determina trigger, escala y métrica:
            //  csat_post_ticket   → tras aprobar ticket, escala 1-5
            //  csat_post_redemption → tras usar canje, escala 1-5
            //  nps                → recurrente cada N días, escala 0-10
            //  custom             → encuesta multi-pregunta diseñada por admin
            $table->enum('tipo', ['csat_post_ticket', 'csat_post_redemption', 'nps', 'custom']);

            $table->string('titulo', 150);
            $table->string('descripcion', 500)->nullable();

            // Texto de la pregunta principal (el rating)
            $table->string('rating_question', 200)->nullable();
            $table->unsignedTinyInteger('rating_scale_max')->default(5); // 5 para CSAT, 10 para NPS

            $table->unsignedInteger('points_reward')->default(0);

            // Ventana en horas tras el trigger; pasada esta, la invitación caduca
            $table->unsignedInteger('response_window_hours')->default(168); // 7 días

            // Bajo este umbral se considera un "low score" → alerta admin
            $table->unsignedTinyInteger('low_score_threshold')->default(2);

            // Para NPS: cada cuántos días se vuelve a invitar al mismo usuario
            $table->unsignedInteger('schedule_interval_days')->nullable();

            // Segmentar a una sola estación. Null = todas.
            $table->foreignId('target_station_id')->nullable()->constrained('stations')->nullOnDelete();

            $table->enum('estado', ['draft', 'active', 'paused', 'archived'])->default('draft')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surveys');
    }
};
