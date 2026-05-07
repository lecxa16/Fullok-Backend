<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    protected $fillable = ['nombre', 'direccion', 'lat', 'lng', 'activa'];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'activa' => 'boolean',
    ];
}
