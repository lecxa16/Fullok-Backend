<?php

namespace Database\Seeders;

use App\Models\Survey;
use Illuminate\Database\Seeder;

class SurveysSeeder extends Seeder
{
    public function run(): void
    {
        // Encuesta CSAT que se dispara automáticamente al aprobar un ticket
        Survey::updateOrCreate(
            ['tipo' => 'csat_post_ticket', 'titulo' => '¿Cómo te atendieron?'],
            [
                'descripcion' => 'Tu opinión nos ayuda a mejorar la atención en cada estación.',
                'rating_question' => '¿Cómo calificas tu experiencia en esta carga?',
                'rating_scale_max' => 5,
                'points_reward' => 20,
                'response_window_hours' => 168, // 7 días
                'low_score_threshold' => 2,
                'estado' => 'active',
            ],
        );

        // NPS trimestral
        Survey::updateOrCreate(
            ['tipo' => 'nps', 'titulo' => '¿Recomendarías Fullok?'],
            [
                'descripcion' => 'Una pregunta rápida que define cómo estamos haciéndolo en general.',
                'rating_question' => '¿Qué tan probable es que recomiendes Fullok a un amigo o familiar?',
                'rating_scale_max' => 10,
                'points_reward' => 50,
                'response_window_hours' => 336, // 14 días
                'low_score_threshold' => 6,
                'schedule_interval_days' => 90,
                'estado' => 'active',
            ],
        );

        // CSAT post-canje
        Survey::updateOrCreate(
            ['tipo' => 'csat_post_redemption', 'titulo' => '¿Cómo fue tu canje?'],
            [
                'descripcion' => 'Cuéntanos cómo te entregaron tu premio.',
                'rating_question' => '¿Qué tan satisfecho estás con tu canje?',
                'rating_scale_max' => 5,
                'points_reward' => 30,
                'response_window_hours' => 168,
                'low_score_threshold' => 2,
                'estado' => 'active',
            ],
        );
    }
}
