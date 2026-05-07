<?php

namespace App\Services;

use App\Models\PointTransaction;
use App\Models\ProgramSetting;

/**
 * Calcula puntos a otorgar por un ticket aprobado y el tier del usuario.
 * Sin escribir nada — funciones puras lectoras.
 */
class TickerCalculator
{
    public function __construct()
    {
    }

    /**
     * Tier del usuario en función de puntos GANADOS en los últimos N meses.
     * Usa expiration_months como ventana móvil.
     *
     * @return array{tier: string, multiplicador: float, puntos_12m: int}
     */
    public function userTier(int $userId): array
    {
        $months = (int) (ProgramSetting::getValue('expiration_months') ?? 12);
        $silverThreshold = (int) (ProgramSetting::getValue('tier_silver_threshold') ?? 1500);
        $goldThreshold = (int) (ProgramSetting::getValue('tier_gold_threshold') ?? 4000);
        $silverMult = (float) (ProgramSetting::getValue('tier_silver_multiplier') ?? 1.25);
        $goldMult = (float) (ProgramSetting::getValue('tier_gold_multiplier') ?? 1.5);

        $puntos = (int) PointTransaction::where('user_id', $userId)
            ->where('tipo', 'ganado')
            ->where('created_at', '>=', now()->subMonths($months))
            ->sum('puntos');

        if ($puntos >= $goldThreshold) {
            return ['tier' => 'oro', 'multiplicador' => $goldMult, 'puntos_12m' => $puntos];
        }
        if ($puntos >= $silverThreshold) {
            return ['tier' => 'plata', 'multiplicador' => $silverMult, 'puntos_12m' => $puntos];
        }
        return ['tier' => 'verde', 'multiplicador' => 1.0, 'puntos_12m' => $puntos];
    }

    /**
     * Calcula puntos a acreditar por un monto.
     *
     * @return array{puntos: int, puntos_base: int, earning_rate: float, multiplicador: float, tier: string}
     */
    public function pointsForAmount(int $userId, float $monto): array
    {
        $earningRate = (float) (ProgramSetting::getValue('earning_rate_pesos_per_point') ?? 20);
        $tierInfo = $this->userTier($userId);

        $puntosBase = $earningRate > 0 ? (int) floor($monto / $earningRate) : 0;
        $puntos = (int) floor($puntosBase * $tierInfo['multiplicador']);

        return [
            'puntos' => $puntos,
            'puntos_base' => $puntosBase,
            'earning_rate' => $earningRate,
            'multiplicador' => $tierInfo['multiplicador'],
            'tier' => $tierInfo['tier'],
        ];
    }
}
