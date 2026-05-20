<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PointTransaction;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\Station;
use App\Models\User;
use App\Services\PointsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PointsController extends Controller
{
    public function __construct(private PointsService $points)
    {
    }

    // ── Cliente: mi balance y mis transacciones ────────────────────────────
    public function myBalance(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        return response()->json([
            'balance' => $this->points->getBalance($userId),
        ]);
    }

    public function myProfileStats(Request $request): JsonResponse
    {
        return response()->json(
            $this->points->getProfileStats($request->user()->id),
        );
    }

    public function myTransactions(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $perPage = min((int) $request->query('per_page', 20), 100);
        return response()->json(
            PointTransaction::where('user_id', $userId)
                ->orderByDesc('created_at')
                ->paginate($perPage),
        );
    }

    // ── Cliente: canjear ──────────────────────────────────────────────────
    public function redeem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reward_id' => ['required', 'integer', 'exists:rewards,id'],
            'station_id' => ['required', 'integer', 'exists:stations,id'],
        ]);

        $reward = Reward::findOrFail($data['reward_id']);
        $station = Station::findOrFail($data['station_id']);

        try {
            $redemption = $this->points->redeem($request->user(), $reward, $station);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'redemption' => $redemption,
            'balance' => $this->points->getBalance($request->user()->id),
        ], 201);
    }

    public function myRedemptions(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $perPage = min((int) $request->query('per_page', 20), 100);
        return response()->json(
            Redemption::with(['reward:id,nombre,tipo,imagen_url', 'station:id,nombre,direccion'])
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->paginate($perPage),
        );
    }

    // ── Admin: listar todos los canjes ────────────────────────────────────
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Redemption::with([
            'user:id,nombre,apellido_paterno,email',
            'reward:id,nombre,tipo,imagen_url',
            'station:id,nombre,direccion',
        ])->orderByDesc('created_at');

        if ($estado = $request->query('estado')) {
            $query->where('estado', $estado);
        }
        if ($stationId = $request->query('station_id')) {
            $query->where('station_id', $stationId);
        }

        $perPage = min((int) $request->query('per_page', 30), 100);
        return response()->json($query->paginate($perPage));
    }

    // ── Admin: marcar canje como usado ────────────────────────────────────
    public function adminMarkUsed(Request $request, Redemption $redemption): JsonResponse
    {
        try {
            $updated = $this->points->markUsed($redemption, $request->user()->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json($updated);
    }

    // ── Admin: ajuste manual de puntos a un usuario ───────────────────────
    public function adminAdjust(Request $request, User $usuario): JsonResponse
    {
        $data = $request->validate([
            'puntos' => ['required', 'integer', 'not_in:0'],
            'descripcion' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        try {
            $tx = $this->points->award(
                $usuario->id,
                $data['puntos'],
                'ajuste_admin',
                $data['descripcion'],
                ['created_by' => $request->user()->id],
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'transaction' => $tx,
            'balance' => $this->points->getBalance($usuario->id),
        ], 201);
    }

    // ── Admin: balance de cualquier usuario ───────────────────────────────
    public function adminUserBalance(User $usuario): JsonResponse
    {
        return response()->json([
            'user_id' => $usuario->id,
            'balance' => $this->points->getBalance($usuario->id),
        ]);
    }
}
