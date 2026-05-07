<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\TickerCalculator;
use App\Services\TicketExtractor;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class TicketController extends Controller
{
    public function __construct(
        private TicketService $tickets,
        private TickerCalculator $calculator,
        private TicketExtractor $extractor,
    ) {
    }

    /**
     * Extrae datos de un ticket vía Claude Vision.
     * No persiste nada — solo lee la imagen y devuelve JSON estructurado para
     * pre-llenar el form. La foto se sube a almacenamiento solo cuando se crea
     * el ticket vía POST /me/tickets.
     */
    public function extract(Request $request): JsonResponse
    {
        $request->validate([
            'foto' => ['required', 'image', 'max:5120'], // 5 MB
        ]);

        try {
            $extracted = $this->extractor->extract($request->file('foto'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($extracted);
    }

    // ── Cliente ───────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'station_id' => ['required', 'integer', 'exists:stations,id'],
            'folio' => ['required', 'string', 'max:60'],
            'monto' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'litros' => ['required', 'numeric', 'min:0.001', 'max:9999.999'],
            'tipo_combustible' => ['required', Rule::in(['magna', 'premium', 'diesel'])],
            'fecha_ticket' => ['required', 'date', 'before_or_equal:today'],
            'foto' => ['nullable', 'image', 'max:5120'], // 5 MB
        ]);

        // Pre-check duplicado para devolver mensaje más claro que el del unique constraint
        $exists = Ticket::where('station_id', $data['station_id'])
            ->where('folio', trim($data['folio']))
            ->whereDate('fecha_ticket', $data['fecha_ticket'])
            ->exists();
        if ($exists) {
            return response()->json([
                'message' => 'Este folio ya fue registrado para esta estación y fecha.',
                'errors' => ['folio' => ['Folio duplicado.']],
            ], 422);
        }

        $ticket = $this->tickets->submit(
            $request->user(),
            $data,
            $request->file('foto'),
        );

        return response()->json($ticket->fresh(['station']), 201);
    }

    public function myIndex(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $perPage = min((int) $request->query('per_page', 20), 100);

        $query = Ticket::with('station:id,nombre,direccion')
            ->where('user_id', $userId)
            ->orderByDesc('fecha_ticket')
            ->orderByDesc('id');

        if ($estado = $request->query('estado')) {
            $query->where('estado', $estado);
        }

        return response()->json($query->paginate($perPage));
    }

    public function myShow(Request $request, Ticket $ticket): JsonResponse
    {
        if ($ticket->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }
        return response()->json($ticket->load(['station', 'revisadoBy:id,email']));
    }

    // ── Admin ─────────────────────────────────────────────────────────────
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Ticket::with([
            'user:id,nombre,apellido_paterno,email',
            'station:id,nombre,direccion',
            'revisadoBy:id,email',
        ])->orderByDesc('created_at');

        if ($estado = $request->query('estado')) {
            $query->where('estado', $estado);
        }
        if ($stationId = $request->query('station_id')) {
            $query->where('station_id', $stationId);
        }
        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        $perPage = min((int) $request->query('per_page', 30), 100);
        return response()->json($query->paginate($perPage));
    }

    public function adminShow(Ticket $ticket): JsonResponse
    {
        $ticket->load([
            'user:id,nombre,apellido_paterno,email',
            'station',
            'revisadoBy:id,email',
        ]);

        // Cálculo previsualizado para que el admin vea cuántos puntos otorgaría al aprobar
        $preview = null;
        if ($ticket->estado === 'pendiente') {
            $preview = $this->calculator->pointsForAmount($ticket->user_id, $ticket->monto);
        }

        return response()->json(array_merge(
            $ticket->toArray(),
            ['preview' => $preview],
        ));
    }

    public function approve(Request $request, Ticket $ticket): JsonResponse
    {
        try {
            $updated = $this->tickets->approve($ticket, $request->user()->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json($updated);
    }

    public function reject(Request $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validate([
            'motivo_rechazo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            $updated = $this->tickets->reject($ticket, $request->user()->id, $data['motivo_rechazo']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json($updated);
    }
}
