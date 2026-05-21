<?php

namespace App\Services;

use App\Models\Redemption;
use App\Models\Station;
use App\Models\Survey;
use App\Models\SurveyInvitation;
use App\Models\SurveyResponse;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orquesta la creación de invitaciones y registro de respuestas.
 *
 * Diseño:
 * - Cada trigger (ticket aprobado, canje usado) busca surveys activos del
 *   tipo correspondiente, opcionalmente filtrados por estación, y crea
 *   una SurveyInvitation por survey. La unique constraint impide duplicar.
 * - La respuesta acredita puntos al usuario via PointsService y, si el
 *   score está bajo el threshold, dispara una notification al admin.
 */
class SurveyService
{
    public function __construct(
        private NotificationService $notifications,
        private PointsService $points,
    ) {}

    /**
     * Llamar desde TicketService::approve después de actualizar el ticket.
     * Crea invitaciones para todos los surveys csat_post_ticket que
     * apliquen al usuario.
     */
    public function inviteAfterTicket(Ticket $ticket): void
    {
        $surveys = Survey::live()
            ->where('tipo', 'csat_post_ticket')
            ->where(function ($q) use ($ticket) {
                $q->whereNull('target_station_id')
                  ->orWhere('target_station_id', $ticket->station_id);
            })
            ->get();

        foreach ($surveys as $survey) {
            $this->createInvitation($survey, $ticket->user_id, [
                'ticket_id' => $ticket->id,
                'station_id' => $ticket->station_id,
            ]);
        }
    }

    public function inviteAfterRedemption(Redemption $redemption): void
    {
        $surveys = Survey::live()
            ->where('tipo', 'csat_post_redemption')
            ->where(function ($q) use ($redemption) {
                $q->whereNull('target_station_id')
                  ->orWhere('target_station_id', $redemption->station_id);
            })
            ->get();

        foreach ($surveys as $survey) {
            $this->createInvitation($survey, $redemption->user_id, [
                'redemption_id' => $redemption->id,
                'station_id' => $redemption->station_id,
            ]);
        }
    }

    /**
     * Trigger genérico para NPS recurrente y custom. Crea una invitación
     * sin contexto de ticket/redemption.
     */
    public function inviteGeneric(Survey $survey, int $userId): ?SurveyInvitation
    {
        if (! $survey->isLive()) return null;
        return $this->createInvitation($survey, $userId, []);
    }

    /**
     * Crea una invitación si no existe ya (la unique constraint nos protege).
     * Notifica al usuario con un push.
     */
    private function createInvitation(Survey $survey, int $userId, array $context): ?SurveyInvitation
    {
        // Para CSAT post-evento la unique constraint dedupe. Para NPS sin
        // contexto, también queremos evitar duplicar si ya tiene una abierta.
        $existing = SurveyInvitation::where('survey_id', $survey->id)
            ->where('user_id', $userId)
            ->where('ticket_id', $context['ticket_id'] ?? null)
            ->where('redemption_id', $context['redemption_id'] ?? null)
            ->first();
        if ($existing) return $existing;

        $invitation = SurveyInvitation::create([
            'survey_id' => $survey->id,
            'user_id' => $userId,
            'ticket_id' => $context['ticket_id'] ?? null,
            'redemption_id' => $context['redemption_id'] ?? null,
            'station_id' => $context['station_id'] ?? null,
            'sent_at' => now(),
            'expires_at' => now()->addHours($survey->response_window_hours),
        ]);

        $this->notifications->notify(
            $userId,
            'survey_invitation',
            $survey->titulo,
            "Comparte tu opinión y gana {$survey->points_reward} pts.",
            [
                'icon' => 'chatbubble-ellipses',
                'deeplink' => "fullok://surveys/{$invitation->id}",
                'prioridad' => 'medium',
                'payload' => [
                    'invitation_id' => $invitation->id,
                    'survey_id' => $survey->id,
                ],
            ],
        );

        return $invitation;
    }

    /**
     * Registra la respuesta y acredita puntos.
     *
     * @param array{score?:?int, feedback?:?string, answers?:?array} $data
     */
    public function submitResponse(SurveyInvitation $invitation, array $data): SurveyResponse
    {
        if (! $invitation->isOpen()) {
            throw new RuntimeException('La encuesta ya fue respondida o expiró.');
        }

        $survey = $invitation->survey;

        // Validación básica del score contra la escala del survey.
        $score = $data['score'] ?? null;
        if ($score !== null) {
            if ($score < 0 || $score > $survey->rating_scale_max) {
                throw new RuntimeException("Score fuera de rango (0-{$survey->rating_scale_max}).");
            }
        }

        return DB::transaction(function () use ($invitation, $survey, $data, $score) {
            $response = SurveyResponse::create([
                'invitation_id' => $invitation->id,
                'survey_id' => $survey->id,
                'user_id' => $invitation->user_id,
                'station_id' => $invitation->station_id,
                'score' => $score,
                'feedback' => $data['feedback'] ?? null,
                'answers' => $data['answers'] ?? null,
                'points_awarded' => $survey->points_reward,
            ]);

            $invitation->update(['responded_at' => now()]);

            // Acreditar puntos al usuario
            if ($survey->points_reward > 0) {
                $this->points->award(
                    $invitation->user_id,
                    $survey->points_reward,
                    'ganado',
                    "Encuesta: {$survey->titulo}",
                    ['created_by' => null],
                );
            }

            // Si rating bajo, notificar a los admins
            if ($score !== null && $score <= $survey->low_score_threshold) {
                $this->alertLowScore($response);
            }

            return $response->fresh();
        });
    }

    /**
     * Notifica a todos los admins activos cuando llega un rating bajo.
     * Para que actúen rápido sobre el cliente o la estación.
     */
    private function alertLowScore(SurveyResponse $response): void
    {
        $adminIds = \App\Models\User::whereHas('role', fn ($q) => $q->where('slug', 'admin'))
            ->where('activo', true)
            ->pluck('id');

        $stationName = $response->station?->nombre ?? 'general';
        $userName = $response->user?->nombre ?? '?';

        foreach ($adminIds as $adminId) {
            $this->notifications->notify(
                $adminId,
                'low_score_alert',
                "Rating bajo en {$stationName}",
                "{$userName} calificó {$response->score} en \"{$response->survey->titulo}\". Revisa el feedback.",
                [
                    'icon' => 'alert-circle',
                    'prioridad' => 'high',
                    'payload' => [
                        'response_id' => $response->id,
                        'survey_id' => $response->survey_id,
                        'station_id' => $response->station_id,
                        'score' => $response->score,
                    ],
                ],
            );
        }

        $response->update(['low_score_alerted_at' => now()]);
    }

    /**
     * Stats agregados para el admin: CSAT promedio, NPS, distribución,
     * tasa de respuesta. Si se pasa stationId, filtra por estación.
     */
    public function aggregateStats(Survey $survey, ?int $stationId = null): array
    {
        $responsesQuery = $survey->responses();
        if ($stationId) {
            $responsesQuery->where('station_id', $stationId);
        }
        $responses = $responsesQuery->get();
        $total = $responses->count();

        $invitationsQuery = $survey->invitations();
        if ($stationId) {
            $invitationsQuery->where('station_id', $stationId);
        }
        $sent = $invitationsQuery->count();

        if ($total === 0) {
            return [
                'total_responses' => 0,
                'total_invitations' => $sent,
                'response_rate' => 0,
                'avg_score' => null,
                'nps_score' => null,
                'csat_score' => null,
                'distribution' => [],
            ];
        }

        $avg = round($responses->avg('score'), 2);

        // Distribución por score (1..5 o 0..10)
        $distribution = [];
        for ($i = 0; $i <= $survey->rating_scale_max; $i++) {
            $distribution[$i] = $responses->where('score', $i)->count();
        }

        $result = [
            'total_responses' => $total,
            'total_invitations' => $sent,
            'response_rate' => $sent > 0 ? round($total / $sent * 100, 1) : 0,
            'avg_score' => $avg,
            'distribution' => $distribution,
        ];

        if ($survey->tipo === 'nps') {
            // NPS: % promotores (9-10) - % detractores (0-6)
            $promotores = $responses->whereIn('score', [9, 10])->count();
            $detractores = $responses->whereBetween('score', [0, 6])->count();
            $result['nps_score'] = round(($promotores - $detractores) / $total * 100, 1);
        } else {
            // CSAT: porcentaje de satisfechos (4-5 sobre 5)
            $satisfied = $responses->whereIn('score', [4, 5])->count();
            $result['csat_score'] = round($satisfied / $total * 100, 1);
        }

        return $result;
    }
}
