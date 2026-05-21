<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preguntas adicionales para encuestas tipo `custom`.
 * Las encuestas CSAT/NPS usan solo el rating_question del survey base.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->unsignedInteger('orden')->default(0);
            $table->string('pregunta', 300);
            // rating_5 (estrellas 1-5), rating_10 (0-10), text (libre),
            // single (radio una opción), multi (varias opciones)
            $table->enum('tipo', ['rating_5', 'rating_10', 'text', 'single', 'multi']);
            $table->json('options')->nullable(); // para single/multi
            $table->boolean('required')->default(false);
            $table->timestamps();

            $table->index(['survey_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_questions');
    }
};
