<?php

namespace App\Services;

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

        return DB::transaction(function () use ($ticket, $adminId) {
            $calc = $this->calculator->pointsForAmount($ticket->user_id, $ticket->monto);

            $tx = $this->points->award(
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

        return $ticket->fresh(['user', 'station', 'revisadoBy']);
    }
}
