<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use App\Models\PushToken;
use App\Models\User;
use App\Services\BroadcastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BroadcastController extends Controller
{
    public function __construct(private BroadcastService $broadcasts) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Broadcast::with('createdBy:id,nombre,email')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'mensaje' => ['required', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:40'],
            'deeplink' => ['nullable', 'string', 'max:200'],
            'prioridad' => ['nullable', Rule::in(['high', 'medium', 'low'])],
            'audience_tier' => ['nullable', Rule::in(['all', 'bronze', 'silver', 'gold'])],
        ]);

        $broadcast = $this->broadcasts->send([
            'titulo' => $data['titulo'],
            'mensaje' => $data['mensaje'],
            'icon' => $data['icon'] ?? null,
            'deeplink' => $data['deeplink'] ?? null,
            'prioridad' => $data['prioridad'] ?? 'medium',
            'audience' => ['tier' => $data['audience_tier'] ?? 'all'],
            'created_by' => $request->user()->id,
        ]);

        return response()->json($broadcast, 201);
    }

    /**
     * Quickstats para el form: cuántos usuarios + cuántos tokens push
     * activos hay por audiencia. Útil para mostrar "Llegará a 142 personas".
     */
    public function audienceStats(): JsonResponse
    {
        $totalUsers = User::where('activo', true)->count();
        $totalTokens = PushToken::count();
        $usersWithToken = PushToken::distinct('user_id')->count('user_id');

        return response()->json([
            'total_users' => $totalUsers,
            'users_with_push' => $usersWithToken,
            'total_tokens' => $totalTokens,
        ]);
    }
}
