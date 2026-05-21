<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Models\SurveyInvitation;
use App\Services\SurveyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class SurveyController extends Controller
{
    public function __construct(private SurveyService $surveys) {}

    // ── Cliente ──────────────────────────────────────────────────────────

    /**
     * Encuestas pendientes de respuesta para el usuario actual.
     * Solo invitaciones abiertas (no respondidas y no expiradas).
     */
    public function myPending(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $invitations = SurveyInvitation::with([
                'survey:id,tipo,titulo,descripcion,rating_question,rating_scale_max,points_reward',
                'station:id,nombre',
            ])
            ->where('user_id', $userId)
            ->whereNull('responded_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->orderByDesc('sent_at')
            ->get();

        return response()->json(['data' => $invitations]);
    }

    public function myShow(Request $request, SurveyInvitation $invitation): JsonResponse
    {
        if ($invitation->user_id !== $request->user()->id) abort(404);
        $invitation->load(['survey.questions', 'station:id,nombre']);
        return response()->json($invitation);
    }

    public function respond(Request $request, SurveyInvitation $invitation): JsonResponse
    {
        if ($invitation->user_id !== $request->user()->id) abort(404);

        $data = $request->validate([
            'score' => ['nullable', 'integer', 'min:0', 'max:10'],
            'feedback' => ['nullable', 'string', 'max:1000'],
            'answers' => ['nullable', 'array'],
        ]);

        try {
            $response = $this->surveys->submitResponse($invitation, $data);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($response, 201);
    }

    // ── Admin ────────────────────────────────────────────────────────────

    public function adminIndex(): JsonResponse
    {
        return response()->json([
            'data' => Survey::with('targetStation:id,nombre')
                ->withCount(['invitations', 'responses'])
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function adminShow(Survey $survey): JsonResponse
    {
        $survey->load(['questions', 'targetStation:id,nombre']);
        return response()->json($survey);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $data['created_by'] = $request->user()->id;
        $survey = Survey::create($data);
        return response()->json($survey, 201);
    }

    public function update(Request $request, Survey $survey): JsonResponse
    {
        $survey->update($this->validateData($request));
        return response()->json($survey);
    }

    public function destroy(Survey $survey): JsonResponse
    {
        $survey->delete();
        return response()->json(['ok' => true]);
    }

    public function toggle(Survey $survey): JsonResponse
    {
        $survey->update([
            'estado' => $survey->estado === 'active' ? 'paused' : 'active',
        ]);
        return response()->json($survey);
    }

    /**
     * Listado de respuestas para una encuesta. Soporta filtros por
     * station_id, score mínimo/máximo y rango de fechas.
     */
    public function responses(Request $request, Survey $survey): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 50), 100);

        $q = $survey->responses()
            ->with(['user:id,nombre,apellido_paterno,email', 'station:id,nombre'])
            ->orderByDesc('created_at');

        if ($s = $request->query('station_id')) $q->where('station_id', $s);
        if ($s = $request->query('min_score')) $q->where('score', '>=', (int) $s);
        if ($s = $request->query('max_score')) $q->where('score', '<=', (int) $s);
        if ($s = $request->query('from')) $q->where('created_at', '>=', $s);
        if ($s = $request->query('to')) $q->where('created_at', '<=', $s);
        if ($request->boolean('only_with_feedback')) $q->whereNotNull('feedback');

        return response()->json($q->paginate($perPage));
    }

    public function stats(Request $request, Survey $survey): JsonResponse
    {
        $stationId = $request->query('station_id');
        return response()->json(
            $this->surveys->aggregateStats($survey, $stationId ? (int) $stationId : null),
        );
    }

    /**
     * Ranking de CSAT por estación para un survey post_ticket / post_redemption.
     */
    public function stationRanking(Survey $survey): JsonResponse
    {
        $rows = $survey->responses()
            ->selectRaw('station_id, AVG(score) as avg_score, COUNT(*) as total')
            ->whereNotNull('station_id')
            ->groupBy('station_id')
            ->having('total', '>=', 1)
            ->orderByDesc('avg_score')
            ->with('station:id,nombre')
            ->get()
            ->map(fn ($r) => [
                'station_id' => $r->station_id,
                'station_nombre' => $r->station->nombre ?? null,
                'avg_score' => round($r->avg_score, 2),
                'total_responses' => (int) $r->total,
            ]);

        return response()->json(['data' => $rows]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'tipo' => ['required', Rule::in(['csat_post_ticket', 'csat_post_redemption', 'nps', 'custom'])],
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'rating_question' => ['nullable', 'string', 'max:200'],
            'rating_scale_max' => ['required', 'integer', 'in:5,10'],
            'points_reward' => ['nullable', 'integer', 'min:0'],
            'response_window_hours' => ['nullable', 'integer', 'min:1'],
            'low_score_threshold' => ['nullable', 'integer', 'min:0'],
            'schedule_interval_days' => ['nullable', 'integer', 'min:1'],
            'target_station_id' => ['nullable', 'integer', 'exists:stations,id'],
            'estado' => ['nullable', Rule::in(['draft', 'active', 'paused', 'archived'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }
}
