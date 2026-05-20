<?php

namespace App\Services;

use App\Models\PointTransaction;
use App\Models\ProgramSetting;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\RewardInventory;
use App\Models\Station;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PointsService
{
    public function getBalance(int $userId): int
    {
        return (int) PointTransaction::where('user_id', $userId)->sum('puntos');
    }

    /**
     * Estadísticas agregadas del usuario para mostrar en perfil/rewards.
     * Incluye balance, tier actual (basado en puntos ganados en últimos 12m
     * vs umbrales de program_settings) y totales de ganados/canjeados.
     */
    public function getProfileStats(int $userId): array
    {
        $balance = (int) PointTransaction::where('user_id', $userId)->sum('puntos');

        $ganadosTotal = (int) PointTransaction::where('user_id', $userId)
            ->where('puntos', '>', 0)
            ->sum('puntos');

        $canjeadosTotal = (int) abs(
            PointTransaction::where('user_id', $userId)
                ->where('puntos', '<', 0)
                ->sum('puntos')
        );

        $ganados12m = (int) PointTransaction::where('user_id', $userId)
            ->where('puntos', '>', 0)
            ->where('created_at', '>=', now()->subMonths(12))
            ->sum('puntos');

        $silverThreshold = (int) (ProgramSetting::getValue('tier_silver_threshold') ?? 1500);
        $goldThreshold = (int) (ProgramSetting::getValue('tier_gold_threshold') ?? 4000);

        if ($ganados12m >= $goldThreshold) {
            $tier = 'gold';
            $nextTier = null;
            $nextThreshold = null;
        } elseif ($ganados12m >= $silverThreshold) {
            $tier = 'silver';
            $nextTier = 'gold';
            $nextThreshold = $goldThreshold;
        } else {
            $tier = 'bronze';
            $nextTier = 'silver';
            $nextThreshold = $silverThreshold;
        }

        return [
            'balance' => $balance,
            'puntos_ganados_total' => $ganadosTotal,
            'puntos_canjeados_total' => $canjeadosTotal,
            'puntos_ganados_12m' => $ganados12m,
            'tier' => $tier,
            'next_tier' => $nextTier,
            'next_tier_threshold' => $nextThreshold,
            'puntos_faltantes_next_tier' => $nextThreshold !== null
                ? max(0, $nextThreshold - $ganados12m)
                : 0,
        ];
    }

    /**
     * Otorga puntos al usuario. Crea una point_transaction tipo "ganado" o "ajuste_admin".
     *
     * @param int    $userId
     * @param int    $puntos        Puntos a sumar (positivo) o restar (negativo).
     * @param string $tipo          ganado|ajuste_admin
     * @param string $descripcion
     * @param array  $opts          ['ticket_id', 'created_by', 'multiplicador_aplicado']
     */
    public function award(
        int $userId,
        int $puntos,
        string $tipo,
        string $descripcion,
        array $opts = [],
    ): PointTransaction {
        return DB::transaction(function () use ($userId, $puntos, $tipo, $descripcion, $opts) {
            $balance = $this->getBalanceForUpdate($userId);
            $nuevo = $balance + $puntos;
            if ($nuevo < 0) {
                throw new RuntimeException('Esta operación dejaría el saldo negativo.');
            }

            $earningSnap = ProgramSetting::getValue('earning_rate_pesos_per_point');
            $valueSnap = ProgramSetting::getValue('point_value_mxn');
            $expiraMeses = (int) (ProgramSetting::getValue('expiration_months') ?? 0);

            return PointTransaction::create([
                'user_id' => $userId,
                'ticket_id' => $opts['ticket_id'] ?? null,
                'redemption_id' => null,
                'tipo' => $tipo,
                'puntos' => $puntos,
                'balance_despues' => $nuevo,
                'multiplicador_aplicado' => $opts['multiplicador_aplicado'] ?? null,
                'earning_rate_snapshot' => $earningSnap,
                'point_value_snapshot' => $valueSnap,
                'descripcion' => $descripcion,
                'expires_at' => $expiraMeses > 0 && $puntos > 0
                    ? now()->addMonths($expiraMeses)
                    : null,
                'created_by' => $opts['created_by'] ?? null,
                'created_at' => now(),
            ]);
        });
    }

    public function redeem(User $user, Reward $reward, Station $station): Redemption
    {
        if (! $reward->isAvailable()) {
            throw new RuntimeException('Esta recompensa no está disponible.');
        }
        if (! $station->activa) {
            throw new RuntimeException('La estación seleccionada no está activa.');
        }

        return DB::transaction(function () use ($user, $reward, $station) {
            $balance = $this->getBalanceForUpdate($user->id);
            if ($balance < $reward->costo_puntos) {
                throw new RuntimeException(
                    "Te faltan " . ($reward->costo_puntos - $balance) . " puntos para esta recompensa.",
                );
            }

            // Bloquea y decrementa inventario de la sucursal elegida
            $inventory = RewardInventory::where('reward_id', $reward->id)
                ->where('station_id', $station->id)
                ->lockForUpdate()
                ->first();

            if (! $inventory || $inventory->stock <= 0) {
                throw new RuntimeException(
                    "No hay disponibilidad de \"{$reward->nombre}\" en {$station->nombre}.",
                );
            }
            $inventory->decrement('stock');

            $redemption = Redemption::create([
                'user_id' => $user->id,
                'reward_id' => $reward->id,
                'station_id' => $station->id,
                'puntos_gastados' => $reward->costo_puntos,
                'codigo_unico' => $this->generateUniqueCode(),
                'estado' => 'emitido',
                'reward_nombre_snapshot' => $reward->nombre,
                'station_nombre_snapshot' => $station->nombre,
            ]);

            $valueSnap = ProgramSetting::getValue('point_value_mxn');

            PointTransaction::create([
                'user_id' => $user->id,
                'redemption_id' => $redemption->id,
                'tipo' => 'canjeado',
                'puntos' => -$reward->costo_puntos,
                'balance_despues' => $balance - $reward->costo_puntos,
                'multiplicador_aplicado' => null,
                'earning_rate_snapshot' => null,
                'point_value_snapshot' => $valueSnap,
                'descripcion' => 'Canje: ' . $reward->nombre . ' en ' . $station->nombre,
                'expires_at' => null,
                'created_at' => now(),
            ]);

            return $redemption->fresh(['reward', 'station']);
        });
    }

    public function markUsed(Redemption $redemption, ?int $adminUserId = null): Redemption
    {
        if ($redemption->estado !== 'emitido') {
            throw new RuntimeException(
                "Este canje ya está en estado '{$redemption->estado}'.",
            );
        }
        $redemption->update([
            'estado' => 'usado',
            'usado_at' => now(),
        ]);
        return $redemption->fresh(['reward', 'station']);
    }

    private function getBalanceForUpdate(int $userId): int
    {
        // Lock pesimista por user_id para evitar race conditions en doble canje
        return (int) PointTransaction::where('user_id', $userId)
            ->lockForUpdate()
            ->sum('puntos');
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(10));
        } while (Redemption::where('codigo_unico', $code)->exists());
        return $code;
    }
}
