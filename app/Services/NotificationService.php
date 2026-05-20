<?php

namespace App\Services;

use App\Models\Notification;

/**
 * Dispatcher central de notificaciones.
 *
 * Cada notificación genera UNA fila en `notifications` (la app la mostrará
 * al consultar GET /me/notifications) y opcionalmente despacha push según
 * la prioridad y el tipo. Las transaccionales (ticket aprobado, rechazado,
 * canje, subida de nivel) se notifican por push siempre.
 */
class NotificationService
{
    /** Tipos que SIEMPRE envían push (transaccionales/críticos). */
    private const PUSH_TYPES = [
        'ticket_approved',
        'ticket_rejected',
        'redemption_done',
        'tier_up',
        'points_expiring', // solo T-7, ver caller
    ];

    public function __construct(private ExpoPushService $push) {}

    /**
     * Crea una notificación in-app y opcionalmente despacha push.
     *
     * @param int    $userId
     * @param string $tipo     Ej. 'ticket_approved'. Ver migration para vocabulario.
     * @param string $titulo
     * @param string $mensaje
     * @param array{
     *     icon?: ?string,
     *     deeplink?: ?string,
     *     payload?: ?array,
     *     prioridad?: 'high'|'medium'|'low',
     *     push?: ?bool,
     *     expires_at?: ?\DateTimeInterface,
     * } $opts
     */
    public function notify(int $userId, string $tipo, string $titulo, string $mensaje, array $opts = []): Notification
    {
        $prioridad = $opts['prioridad'] ?? 'medium';
        $shouldPush = $opts['push'] ?? in_array($tipo, self::PUSH_TYPES, true);

        $notif = Notification::create([
            'user_id' => $userId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'icon' => $opts['icon'] ?? null,
            'deeplink' => $opts['deeplink'] ?? null,
            'payload' => $opts['payload'] ?? null,
            'prioridad' => $prioridad,
            'expires_at' => $opts['expires_at'] ?? null,
        ]);

        if ($shouldPush) {
            $sent = $this->push->sendToUser($userId, [
                'title' => $titulo,
                'body' => $mensaje,
                'data' => array_merge(
                    ['notification_id' => $notif->id, 'tipo' => $tipo],
                    $opts['deeplink'] ? ['deeplink' => $opts['deeplink']] : [],
                    $opts['payload'] ?? [],
                ),
                'priority' => $prioridad === 'high' ? 'high' : 'default',
            ]);
            if ($sent > 0) {
                $notif->update(['push_sent_at' => now()]);
            }
        }

        return $notif;
    }
}
