<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\TickerCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    public function __construct(private TickerCalculator $calc) {}

    /**
     * Catálogo público para la app móvil.
     * Solo devuelve promociones activas filtradas por el tier del usuario.
     */
    public function index(Request $request): JsonResponse
    {
        $tier = $this->calc->userTier($request->user()->id)['tier'];
        $rank = ['bronze' => 1, 'silver' => 2, 'gold' => 3];

        $promos = Promotion::live()
            ->where(function ($q) use ($tier, $rank) {
                $q->whereNull('min_tier');
                foreach ($rank as $t => $r) {
                    if ($r <= ($rank[$tier] ?? 0)) {
                        $q->orWhere('min_tier', $t);
                    }
                }
            })
            ->orderBy('prioridad')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $promos]);
    }

    public function show(Promotion $promotion): JsonResponse
    {
        return response()->json($promotion);
    }

    // ── Admin ──────────────────────────────────────────────────────────────

    public function adminIndex(): JsonResponse
    {
        return response()->json([
            'data' => Promotion::orderByDesc('created_at')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $data['created_by'] = $request->user()->id;
        $promo = Promotion::create($data);
        return response()->json($promo, 201);
    }

    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $promotion->update($this->validateData($request));
        return response()->json($promotion);
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        $promotion->delete();
        return response()->json(['ok' => true]);
    }

    public function pause(Promotion $promotion): JsonResponse
    {
        $promotion->update([
            'estado' => $promotion->estado === 'paused' ? 'active' : 'paused',
        ]);
        return response()->json($promotion);
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'tipo' => ['required', Rule::in(['showcase', 'multiplier', 'bonus_goal'])],
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'imagen_url' => ['nullable', 'string', 'max:500'],
            'color_hex' => ['nullable', 'string', 'max:7'],
            'emoji' => ['nullable', 'string', 'max:8'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'estado' => ['sometimes', Rule::in(['draft', 'scheduled', 'active', 'expired', 'paused'])],
            'prioridad' => ['nullable', 'integer', 'min:0'],

            'multiplier_value' => ['nullable', 'numeric', 'min:1', 'max:10'],
            'fuel_type_filter' => ['nullable', Rule::in(['magna', 'premium', 'diesel'])],
            'min_tier' => ['nullable', Rule::in(['bronze', 'silver', 'gold'])],

            'goal_type' => ['nullable', Rule::in(['tickets_count', 'total_amount'])],
            'goal_target' => ['nullable', 'integer', 'min:1'],
            'bonus_points' => ['nullable', 'integer', 'min:1'],

            'max_redemptions_per_user' => ['nullable', 'integer', 'min:1'],
            'total_budget_points' => ['nullable', 'integer', 'min:1'],
            'deeplink_url' => ['nullable', 'string', 'max:200'],
        ]);

        // Reglas de coherencia según tipo
        if ($data['tipo'] === 'multiplier' && empty($data['multiplier_value'])) {
            abort(422, 'Una promo multiplier requiere multiplier_value.');
        }
        if ($data['tipo'] === 'bonus_goal') {
            if (empty($data['goal_type']) || empty($data['goal_target']) || empty($data['bonus_points'])) {
                abort(422, 'Una promo bonus_goal requiere goal_type, goal_target y bonus_points.');
            }
        }

        return $data;
    }
}
