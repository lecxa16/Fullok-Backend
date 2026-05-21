<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'user_id', 'ticket_id', 'tax_profile_id', 'estado',
        'facturapi_invoice_id', 'folio_fiscal_uuid', 'serie', 'folio',
        'monto_total', 'uso_cfdi', 'payment_form',
        'pdf_url', 'xml_url',
        'emitida_at', 'cancelada_at', 'motivo_cancelacion',
        'error_message', 'pac_response',
    ];

    protected $casts = [
        'monto_total' => 'decimal:2',
        'emitida_at' => 'datetime',
        'cancelada_at' => 'datetime',
        'pac_response' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function taxProfile(): BelongsTo
    {
        return $this->belongsTo(TaxProfile::class);
    }
}
