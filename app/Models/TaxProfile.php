<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxProfile extends Model
{
    protected $fillable = [
        'user_id', 'rfc', 'razon_social', 'tipo_persona',
        'nombre', 'apellido_paterno', 'apellido_materno', 'nombre_fiscal',
        'regimen_fiscal_sat', 'uso_cfdi_default', 'cp_fiscal', 'email_facturacion',
        'alias', 'is_default', 'facturapi_customer_id', 'validated_at',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'validated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
