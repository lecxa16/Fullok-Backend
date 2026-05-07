<?php

namespace Database\Seeders;

use App\Models\Reward;
use App\Models\RewardInventory;
use App\Models\Station;
use Illuminate\Database\Seeder;

class RewardInventorySeeder extends Seeder
{
    public function run(): void
    {
        $stations = Station::where('activa', true)->orderBy('id')->get();
        if ($stations->isEmpty()) {
            $this->command?->warn('No hay estaciones activas. Saltando.');
            return;
        }

        $rewards = Reward::all();
        foreach ($rewards as $reward) {
            // Si el reward tiene stock global, lo distribuye uniformemente.
            $total = (int) ($reward->stock ?? 0);
            if ($total <= 0) {
                continue;
            }

            // Distribución uniforme con remainder a las primeras N sucursales.
            $count = $stations->count();
            $base = intdiv($total, $count);
            $remainder = $total % $count;

            foreach ($stations as $i => $station) {
                $extra = $i < $remainder ? 1 : 0;
                $stock = $base + $extra;

                RewardInventory::updateOrCreate(
                    ['reward_id' => $reward->id, 'station_id' => $station->id],
                    ['stock' => $stock],
                );
            }

            // Mantener compatibilidad: el campo rewards.stock = SUM de inventarios.
            $reward->update(['stock' => $reward->fresh()->totalStock()]);
        }
    }
}
