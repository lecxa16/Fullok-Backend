<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PointTransaction;
use App\Models\Promotion;
use App\Models\PushToken;
use App\Models\Redemption;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $hoy = now()->startOfDay();
        $hace30d = now()->subDays(30);

        $usuariosActivos = User::where('activo', true)->count();
        $usuariosNuevos30d = User::where('created_at', '>=', $hace30d)->count();

        $ticketsPendientes = Ticket::where('estado', 'pendiente')->count();
        $ticketsAprobadosHoy = Ticket::where('estado', 'aprobado')
            ->where('revisado_at', '>=', $hoy)->count();
        $ticketsRechazadosHoy = Ticket::where('estado', 'rechazado')
            ->where('revisado_at', '>=', $hoy)->count();

        $puntosAcreditados30d = (int) PointTransaction::where('tipo', 'ganado')
            ->where('created_at', '>=', $hace30d)
            ->sum('puntos');

        $canjesEmitidos = Redemption::where('estado', 'emitido')->count();
        $canjesHoy = Redemption::where('created_at', '>=', $hoy)->count();

        $promosActivas = Promotion::live()->count();
        $facturasMes = Invoice::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('estado', 'generated')->count();

        $devicesConPush = PushToken::distinct('user_id')->count('user_id');

        // Últimos 7 días — count de tickets aprobados por día (mini chart)
        $serieTickets = collect(range(6, 0))->map(function ($d) {
            $fecha = now()->subDays($d)->startOfDay();
            $proximo = $fecha->copy()->addDay();
            return [
                'fecha' => $fecha->toDateString(),
                'count' => Ticket::where('estado', 'aprobado')
                    ->where('revisado_at', '>=', $fecha)
                    ->where('revisado_at', '<', $proximo)
                    ->count(),
            ];
        });

        return response()->json([
            'usuarios_activos' => $usuariosActivos,
            'usuarios_nuevos_30d' => $usuariosNuevos30d,
            'tickets_pendientes' => $ticketsPendientes,
            'tickets_aprobados_hoy' => $ticketsAprobadosHoy,
            'tickets_rechazados_hoy' => $ticketsRechazadosHoy,
            'puntos_acreditados_30d' => $puntosAcreditados30d,
            'canjes_emitidos' => $canjesEmitidos,
            'canjes_hoy' => $canjesHoy,
            'promos_activas' => $promosActivas,
            'facturas_mes' => $facturasMes,
            'devices_con_push' => $devicesConPush,
            'serie_tickets_7d' => $serieTickets,
        ]);
    }
}
