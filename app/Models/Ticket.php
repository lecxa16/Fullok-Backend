<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Ticket extends Model
{
    protected $fillable = [
        'user_id', 'station_id', 'folio', 'monto', 'litros', 'tipo_combustible',
        'fecha_ticket', 'foto_path', 'estado', 'motivo_rechazo',
        'revisado_por', 'revisado_at',
        'puntos_acreditados', 'multiplicador_aplicado',
        'earning_rate_snapshot', 'tier_snapshot',
    ];

    protected $casts = [
        'monto' => 'float',
        'litros' => 'float',
        'fecha_ticket' => 'date',
        'revisado_at' => 'datetime',
        'puntos_acreditados' => 'integer',
        'multiplicador_aplicado' => 'float',
        'earning_rate_snapshot' => 'float',
    ];

    protected $appends = ['foto_url', 'can_invoice', 'invoice_blocked_reason'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function revisadoBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto_path
            ? Storage::disk('public')->url($this->foto_path)
            : null;
    }

    /**
     * Si el ticket puede facturarse desde la app: aprobado, sin factura
     * existente, y dentro de la ventana SAT (o con la validación
     * desactivada globalmente).
     */
    public function getCanInvoiceAttribute(): bool
    {
        return $this->invoiceBlockedReason() === null;
    }

    public function getInvoiceBlockedReasonAttribute(): ?string
    {
        return $this->invoiceBlockedReason();
    }

    private function invoiceBlockedReason(): ?string
    {
        if ($this->estado !== 'aprobado') {
            return 'no_aprobado';
        }
        // Si ya hay una factura no cancelada/no errored, está bloqueado
        $hasInvoice = Invoice::where('ticket_id', $this->id)
            ->whereNotIn('estado', ['cancelled', 'error'])
            ->exists();
        if ($hasInvoice) {
            return 'ya_facturado';
        }

        $strict = filter_var(
            ProgramSetting::getValue('invoicing_strict_window', 'true'),
            FILTER_VALIDATE_BOOLEAN,
        );
        if ($strict && $this->fecha_ticket) {
            $fecha = \Carbon\Carbon::parse($this->fecha_ticket);
            $limite = $fecha->copy()->addMonth()->endOfMonth();
            if (now()->gt($limite)) {
                return 'fuera_de_ventana';
            }
        }
        return null;
    }
}
