<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use App\Models\RewardInventory;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RewardInventoryController extends Controller
{
    /**
     * Inventario completo de un reward por sucursal (admin).
     * Devuelve TODAS las sucursales activas con su stock (0 si no tienen).
     */
    public function show(Reward $reward): JsonResponse
    {
        $stations = Station::orderBy('nombre')->get(['id', 'nombre', 'direccion', 'activa']);
        $inventories = $reward->inventories()->get()->keyBy('station_id');

        $rows = $stations->map(function ($s) use ($inventories) {
            $inv = $inventories->get($s->id);
            return [
                'station_id' => $s->id,
                'station_nombre' => $s->nombre,
                'station_direccion' => $s->direccion,
                'station_activa' => $s->activa,
                'stock' => $inv ? $inv->stock : 0,
            ];
        });

        return response()->json([
            'reward_id' => $reward->id,
            'reward_nombre' => $reward->nombre,
            'total' => (int) $rows->sum('stock'),
            'data' => $rows->values(),
        ]);
    }

    /**
     * Bulk update del inventario de un reward (admin).
     * Body: {"items": [{"station_id": 1, "stock": 5}, ...]}
     */
    public function update(Request $request, Reward $reward): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.station_id' => ['required', 'integer', 'exists:stations,id'],
            'items.*.stock' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $reward) {
            foreach ($data['items'] as $item) {
                RewardInventory::updateOrCreate(
                    ['reward_id' => $reward->id, 'station_id' => $item['station_id']],
                    ['stock' => $item['stock']],
                );
            }
        });

        return $this->show($reward->fresh());
    }

    /**
     * Disponibilidad por sucursal del reward — endpoint para la app móvil.
     * Devuelve solo sucursales activas con stock > 0.
     */
    public function availability(Reward $reward): JsonResponse
    {
        $rows = RewardInventory::with('station:id,nombre,direccion,lat,lng,activa')
            ->where('reward_id', $reward->id)
            ->where('stock', '>', 0)
            ->get()
            ->filter(fn ($i) => $i->station && $i->station->activa)
            ->map(fn ($i) => [
                'station_id' => $i->station->id,
                'nombre' => $i->station->nombre,
                'direccion' => $i->station->direccion,
                'lat' => (float) $i->station->lat,
                'lng' => (float) $i->station->lng,
                'stock' => $i->stock,
            ])
            ->values();

        return response()->json([
            'reward_id' => $reward->id,
            'reward_nombre' => $reward->nombre,
            'total' => (int) $rows->sum('stock'),
            'data' => $rows,
        ]);
    }
}
