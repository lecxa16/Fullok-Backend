<?php

namespace Database\Seeders;

use App\Models\ProgramSetting;
use Illuminate\Database\Seeder;

class ProgramSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ── Earning ─────────────────────────────────────────────────────
            [
                'key' => 'earning_rate_pesos_per_point',
                'value' => '20',
                'type' => 'number',
                'group' => 'earning',
                'label' => 'Pesos por punto',
                'description' => 'MXN gastados en gasolina/diésel para ganar 1 punto.',
                'sort_order' => 10,
            ],

            // ── Valor del punto al canjear ─────────────────────────────────
            [
                'key' => 'point_value_mxn',
                'value' => '0.20',
                'type' => 'number',
                'group' => 'point_value',
                'label' => 'Valor del punto (MXN)',
                'description' => 'Cuánto vale 1 punto al canjearlo. Ej. 0.20 = 100 pts equivalen a $20 MXN.',
                'sort_order' => 20,
            ],

            // ── Tiers ─────────────────────────────────────────────────────
            [
                'key' => 'tier_silver_threshold',
                'value' => '1500',
                'type' => 'number',
                'group' => 'tiers',
                'label' => 'Plata: puntos en 12 meses',
                'description' => 'Puntos acumulados en 12m para alcanzar el nivel Plata.',
                'sort_order' => 30,
            ],
            [
                'key' => 'tier_silver_multiplier',
                'value' => '1.25',
                'type' => 'number',
                'group' => 'tiers',
                'label' => 'Plata: multiplicador',
                'description' => 'Factor aplicado a los puntos ganados por usuarios Plata. 1.0 = sin bonus.',
                'sort_order' => 31,
            ],
            [
                'key' => 'tier_gold_threshold',
                'value' => '4000',
                'type' => 'number',
                'group' => 'tiers',
                'label' => 'Oro: puntos en 12 meses',
                'description' => 'Puntos acumulados en 12m para alcanzar el nivel Oro.',
                'sort_order' => 32,
            ],
            [
                'key' => 'tier_gold_multiplier',
                'value' => '1.5',
                'type' => 'number',
                'group' => 'tiers',
                'label' => 'Oro: multiplicador',
                'description' => 'Factor aplicado a los puntos ganados por usuarios Oro.',
                'sort_order' => 33,
            ],

            // ── Expiración ─────────────────────────────────────────────────
            [
                'key' => 'expiration_months',
                'value' => '12',
                'type' => 'number',
                'group' => 'expiration',
                'label' => 'Meses para expirar',
                'description' => 'Si el usuario no tiene actividad en N meses, sus puntos expiran.',
                'sort_order' => 40,
            ],

            // ── Bonificadores ─────────────────────────────────────────────
            [
                'key' => 'welcome_bonus',
                'value' => '100',
                'type' => 'number',
                'group' => 'bonuses',
                'label' => 'Bono de bienvenida',
                'description' => 'Puntos otorgados al registrarse.',
                'sort_order' => 50,
            ],
            [
                'key' => 'first_charge_of_month_bonus',
                'value' => '50',
                'type' => 'number',
                'group' => 'bonuses',
                'label' => 'Bono primera carga del mes',
                'description' => 'Puntos extra al registrar la primera carga aprobada del mes.',
                'sort_order' => 51,
            ],
            [
                'key' => 'profile_complete_bonus',
                'value' => '200',
                'type' => 'number',
                'group' => 'bonuses',
                'label' => 'Bono perfil completo + RFC',
                'description' => 'Puntos al completar perfil y vincular datos fiscales.',
                'sort_order' => 52,
            ],

            // ── Defaults del simulador ─────────────────────────────────────
            [
                'key' => 'sim_clientes_default',
                'value' => '10000',
                'type' => 'number',
                'group' => 'simulator_defaults',
                'label' => 'Clientes activos / mes',
                'description' => 'Supuesto por defecto para el simulador.',
                'sort_order' => 100,
            ],
            [
                'key' => 'sim_frecuencia_default',
                'value' => '6',
                'type' => 'number',
                'group' => 'simulator_defaults',
                'label' => 'Cargas por cliente / mes',
                'description' => 'Frecuencia esperada de cargas por cliente activo.',
                'sort_order' => 101,
            ],
            [
                'key' => 'sim_ticket_promedio_default',
                'value' => '700',
                'type' => 'number',
                'group' => 'simulator_defaults',
                'label' => 'Ticket promedio (MXN)',
                'description' => 'Monto promedio por carga, en pesos.',
                'sort_order' => 102,
            ],
            [
                'key' => 'sim_breakage_default',
                'value' => '0.25',
                'type' => 'number',
                'group' => 'simulator_defaults',
                'label' => 'Breakage esperado (0-1)',
                'description' => 'Fracción de puntos emitidos que NUNCA se canjean. Típico 0.15 - 0.30.',
                'sort_order' => 103,
            ],
            [
                'key' => 'sim_multiplicador_efectivo_default',
                'value' => '1.10',
                'type' => 'number',
                'group' => 'simulator_defaults',
                'label' => 'Multiplicador efectivo promedio',
                'description' => 'Considera mezcla de tiers + bonos temporales aplicados sobre el ratio base.',
                'sort_order' => 104,
            ],
        ];

        foreach ($settings as $s) {
            ProgramSetting::updateOrCreate(['key' => $s['key']], $s);
        }
    }
}
