<?php

namespace App\Services;

use App\Models\Broadcast;
use App\Models\Notification;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de envío masivo de notificaciones (broadcasts).
 *
 * Hace dos cosas en el mismo flujo:
 *   1. Crea una fila Notification por cada usuario en la audiencia
 *      (para que aparezca en su modal in-app aunque no estén online).
 *   2. Envía push a todos los expo tokens de esos usuarios en batches.
 *
 * Se llama desde el endpoint /admin/broadcasts.
 * El admin espera al request, así que para audiencias pequeñas-medianas
 * (hasta unos cientos de usuarios) es síncrono. Si crece, mover a queue.
 */
class BroadcastService
{
    public function __construct(private ExpoPushService $push) {}

    /**
     * @param array{
     *     titulo: string, mensaje: string,
     *     icon?: ?string, deeplink?: ?string,
     *     prioridad?: 'high'|'medium'|'low',
     *     audience?: array{tier?: 'all'|'bronze'|'silver'|'gold'}|null,
     *     created_by?: ?int
     * } $opts
     */
    public function send(array $opts): Broadcast
    {
        $titulo = $opts['titulo'];
        $mensaje = $opts['mensaje'];
        $prioridad = $opts['prioridad'] ?? 'medium';

        $broadcast = Broadcast::create([
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'icon' => $opts['icon'] ?? null,
            'deeplink' => $opts['deeplink'] ?? null,
            'prioridad' => $prioridad,
            'audience_filter' => $opts['audience'] ?? null,
            'created_by' => $opts['created_by'] ?? null,
        ]);

        $userIds = $this->resolveAudience($opts['audience'] ?? null);
        $broadcast->total_users = count($userIds);

        // 1) Crear Notification por user en bulk
        if (! empty($userIds)) {
            $now = now();
            $rows = array_map(fn ($uid) => [
                'user_id' => $uid,
                'tipo' => 'broadcast',
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'icon' => $opts['icon'] ?? null,
                'deeplink' => $opts['deeplink'] ?? null,
                'payload' => json_encode(['broadcast_id' => $broadcast->id]),
                'prioridad' => $prioridad,
                'created_at' => $now,
                'updated_at' => $now,
            ], $userIds);

            // Bulk insert por chunks para no estresar MySQL
            foreach (array_chunk($rows, 500) as $chunk) {
                Notification::insert($chunk);
            }
        }

        // 2) Push a todos los tokens activos de los usuarios del audience
        $tokens = PushToken::whereIn('user_id', $userIds)->pluck('token')->all();
        $sent = $this->push->sendMany($tokens, [
            'title' => $titulo,
            'body' => $mensaje,
            'priority' => $prioridad === 'high' ? 'high' : 'default',
            'data' => array_filter([
                'broadcast_id' => $broadcast->id,
                'tipo' => 'broadcast',
                'deeplink' => $opts['deeplink'] ?? null,
            ]),
        ]);

        $broadcast->update([
            'push_sent_count' => $sent,
            'sent_at' => now(),
        ]);

        return $broadcast->fresh();
    }

    /**
     * Resuelve el array de user_ids según el filtro.
     * Acepta: { tier: 'all' } | { tier: 'bronze'|'silver'|'gold' }
     */
    private function resolveAudience(?array $audience): array
    {
        $tier = $audience['tier'] ?? 'all';

        if ($tier === 'all' || ! $tier) {
            return User::where('activo', true)->pluck('id')->all();
        }

        // Filtro por tier: calculamos puntos ganados 12m por usuario y
        // comparamos contra thresholds. Se hace en una query agregada.
        $silver = (int) (\App\Models\ProgramSetting::getValue('tier_silver_threshold') ?? 1500);
        $gold = (int) (\App\Models\ProgramSetting::getValue('tier_gold_threshold') ?? 4000);

        $rows = DB::table('users')
            ->leftJoin('point_transactions', function ($j) {
                $j->on('point_transactions.user_id', '=', 'users.id')
                    ->where('point_transactions.puntos', '>', 0)
                    ->where('point_transactions.created_at', '>=', now()->subMonths(12));
            })
            ->where('users.activo', true)
            ->groupBy('users.id')
            ->select('users.id', DB::raw('COALESCE(SUM(point_transactions.puntos), 0) as ganados'))
            ->get();

        $matches = [];
        foreach ($rows as $r) {
            $userTier = $r->ganados >= $gold ? 'gold'
                : ($r->ganados >= $silver ? 'silver' : 'bronze');
            if ($userTier === $tier) {
                $matches[] = $r->id;
            }
        }
        return $matches;
    }
}
