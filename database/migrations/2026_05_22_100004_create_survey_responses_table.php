<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->unique()->constrained('survey_invitations')->cascadeOnDelete();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('station_id')->nullable()->constrained('stations')->nullOnDelete();

            // Rating principal (1-5 o 0-10 según el tipo del survey)
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('feedback')->nullable();

            // Respuestas adicionales para encuestas custom (id_pregunta → respuesta)
            $table->json('answers')->nullable();

            $table->unsignedInteger('points_awarded')->default(0);

            // Si el score era ≤ threshold, marca cuándo se notificó al admin
            $table->timestamp('low_score_alerted_at')->nullable();

            $table->timestamps();

            $table->index(['survey_id', 'score', 'created_at']);
            $table->index(['station_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
