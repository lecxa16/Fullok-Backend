<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada invitación = 1 oportunidad de respuesta. La crea el sistema cuando
 * dispara el trigger del survey (ej. ticket_approved). Tiene window de
 * expiración. El usuario responde → se crea una survey_response asociada
 * y se marca responded_at.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('survey_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Contexto que disparó la invitación
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->foreignId('redemption_id')->nullable()->constrained('redemptions')->nullOnDelete();
            $table->foreignId('station_id')->nullable()->constrained('stations')->nullOnDelete();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'responded_at', 'expires_at']);
            $table->index(['survey_id', 'responded_at']);
            // No queremos duplicar invitaciones para el mismo evento
            $table->unique(['survey_id', 'user_id', 'ticket_id', 'redemption_id'], 'unique_survey_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_invitations');
    }
};
