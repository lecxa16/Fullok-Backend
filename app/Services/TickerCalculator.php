<?php

namespace App\Services;

use App\Models\PointTransaction;
use App\Models\ProgramSetting;
use App\Models\Promotion;

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
            return ['tier' => 'gold', 'multiplicador' => $goldMult, 'puntos_12m' => $puntos];
        }
        if ($puntos >= $silverThreshold) {
            return ['tier' => 'silver', 'multiplicador' => $silverMult, 'puntos_12m' => $puntos];
        }
        return ['tier' => 'bronze', 'multiplicador' => 1.0, 'puntos_12m' => $puntos];
    }

    /**
     * Calcula puntos a acreditar por un monto.
     *
     * Si pasas $fuel se evalúan promociones tipo `multiplier` activas que
     * apliquen al user y al combustible. Aplica el multiplier MÁS ALTO entre
     * tier y promo (no se suman) — política del expert de loyalty.
     *
     * @return array{
     *     puntos: int, puntos_base: int, earning_rate: float,
     *     multiplicador: float, tier: string,
     *     promo_id: ?int, multiplier_source: 'tier'|'promo'
     * }
     */
    public function pointsForAmount(int $userId, float $monto, ?string $fuel = null): array
    {
        $earningRate = (float) (ProgramSetting::getValue('earning_rate_pesos_per_point') ?? 20);
        $tierInfo = $this->userTier($userId);

        $promoMult = null;
        $promoId = null;
        if ($fuel) {
            $best = $this->bestActiveMultiplier($tierInfo['tier'], $fuel);
            if ($best) {
                $promoMult = (float) $best->multiplier_value;
                $promoId = $best->id;
            }
        }

        // Política: NO se suman. Se aplica el más alto.
        $finalMult = $tierInfo['multiplicador'];
        $source = 'tier';
        if ($promoMult !== null && $promoMult > $finalMult) {
            $finalMult = $promoMult;
            $source = 'promo';
        }

        $puntosBase = $earningRate > 0 ? (int) floor($monto / $earningRate) : 0;
        $puntos = (int) floor($puntosBase * $finalMult);

        return [
            'puntos' => $puntos,
            'puntos_base' => $puntosBase,
            'earning_rate' => $earningRate,
            'multiplicador' => $finalMult,
            'tier' => $tierInfo['tier'],
            'promo_id' => $promoId,
            'multiplier_source' => $source,
        ];
    }

    /**
     * Devuelve la promoción `multiplier` activa con el mayor multiplier_value
     * que aplique a este (tier, fuel). Null si no hay ninguna.
     */
    private function bestActiveMultiplier(string $tier, string $fuel): ?Promotion
    {
        $candidatas = Promotion::live()
            ->where('tipo', 'multiplier')
            ->whereNotNull('multiplier_value')
            ->orderByDesc('multiplier_value')
            ->get();

        foreach ($candidatas as $p) {
            if ($p->appliesTo($tier, $fuel)) return $p;
        }
        return null;
    }
}
