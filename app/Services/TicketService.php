<?php

namespace App\Services;

use App\Models\Promotion;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class TicketService
{
    public function __construct(
        private PointsService $points,
        private TickerCalculator $calculator,
        private NotificationService $notifications,
        private SurveyService $surveys,
    ) {
    }

    public function submit(User $user, array $data, ?UploadedFile $foto = null): Ticket
    {
        $fotoPath = null;
        if ($foto) {
            $fotoPath = $foto->store('tickets/' . $user->id, 'public');
        }

        return Ticket::create([
            'user_id' => $user->id,
            'station_id' => $data['station_id'],
            'folio' => trim($data['folio']),
            'monto' => $data['monto'],
            'litros' => $data['litros'],
            'tipo_combustible' => $data['tipo_combustible'],
            'fecha_ticket' => $data['fecha_ticket'],
            'foto_path' => $fotoPath,
            'estado' => 'pendiente',
        ]);
    }

    public function approve(Ticket $ticket, int $adminId): Ticket
    {
        if ($ticket->estado !== 'pendiente') {
            throw new RuntimeException("El ticket ya está en estado '{$ticket->estado}'.");
        }

        // Capturamos tier ANTES del award para detectar subida de nivel
        $tierBefore = $this->points->getProfileStats($ticket->user_id)['tier'];

        $fresh = DB::transaction(function () use ($ticket, $adminId) {
            $calc = $this->calculator->pointsForAmount(
                $ticket->user_id,
                $ticket->monto,
                $ticket->tipo_combustible,
            );

            $this->points->award(
                $ticket->user_id,
                $calc['puntos'],
                'ganado',
                "Carga {$ticket->tipo_combustible} en {$ticket->station->nombre} (folio {$ticket->folio})",
                [
                    'ticket_id' => $ticket->id,
                    'multiplicador_aplicado' => $calc['multiplicador'],
                    'created_by' => $adminId,
                ],
            );

            // Si la promo aplicada generó puntos extra vs tier puro, registrar el consumo.
            if (($calc['multiplier_source'] ?? null) === 'promo' && ! empty($calc['promo_id'])) {
                Promotion::where('id', $calc['promo_id'])->increment('points_issued', $calc['puntos']);
            }

            $ticket->update([
                'estado' => 'aprobado',
                'revisado_por' => $adminId,
                'revisado_at' => now(),
                'puntos_acreditados' => $calc['puntos'],
                'multiplicador_aplicado' => $calc['multiplicador'],
                'earning_rate_snapshot' => $calc['earning_rate'],
                'tier_snapshot' => $calc['tier'],
            ]);

            return $ticket->fresh(['user', 'station', 'revisadoBy']);
        });

        $this->notifications->notify(
            $ticket->user_id,
            'ticket_approved',
            '¡Ticket aprobado!',
            "+{$fresh->puntos_acreditados} pts acreditados por tu carga en {$fresh->station->nombre}.",
            [
                'icon' => 'checkmark-circle',
                'deeplink' => "fullok://tickets/{$fresh->id}",
                'prioridad' => 'high',
                'payload' => ['ticket_id' => $fresh->id, 'puntos' => $fresh->puntos_acreditados],
            ],
        );

        // Disparar invitación a CSAT post-ticket (si hay surveys activos)
        $this->surveys->inviteAfterTicket($fresh);

        // Si el tier subió, notificar
        $tierAfter = $this->points->getProfileStats($ticket->user_id)['tier'];
        if ($tierBefore !== $tierAfter) {
            $labels = ['bronze' => 'BRONCE', 'silver' => 'PLATA', 'gold' => 'ORO'];
            $this->notifications->notify(
                $ticket->user_id,
                'tier_up',
                '¡Subiste de nivel! 🎉',
                "Ahora eres nivel " . ($labels[$tierAfter] ?? strtoupper($tierAfter)) . ". Disfruta multiplicadores más altos en tus puntos.",
                [
                    'icon' => 'trophy',
                    'deeplink' => 'fullok://rewards',
                    'prioridad' => 'high',
                    'payload' => ['tier' => $tierAfter, 'tier_previo' => $tierBefore],
                ],
            );
        }

        return $fresh;
    }

    public function reject(Ticket $ticket, int $adminId, string $motivo): Ticket
    {
        if ($ticket->estado !== 'pendiente') {
            throw new RuntimeException("El ticket ya está en estado '{$ticket->estado}'.");
        }

        $ticket->update([
            'estado' => 'rechazado',
            'motivo_rechazo' => $motivo,
            'revisado_por' => $adminId,
            'revisado_at' => now(),
        ]);

        $fresh = $ticket->fresh(['user', 'station', 'revisadoBy']);

        $this->notifications->notify(
            $ticket->user_id,
            'ticket_rejected',
            'Tu ticket fue rechazado',
            $motivo,
            [
                'icon' => 'close-circle',
                'deeplink' => "fullok://tickets/{$fresh->id}",
                'prioridad' => 'high',
                'payload' => ['ticket_id' => $fresh->id, 'motivo' => $motivo],
            ],
        );

        return $fresh;
    }
}
