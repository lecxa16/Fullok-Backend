<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'role_id',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'email',
        'telefono',
        'password',
        'genero',
        'fecha_nacimiento',
        'activo',
    ];

    protected $hidden = [
        'password',
    ];

    // Carga el rol automáticamente en cada consulta
    protected $with = ['role'];

    protected function casts(): array
    {
        return [
            'password'         => 'hashed',
            'activo'           => 'boolean',
            'fecha_nacimiento' => 'date',
        ];
    }

    // ── Relaciones ─────────────────────────────────────────────

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    // ── Atributos calculados ────────────────────────────────────

    public function getNombreCompletoAttribute(): string
    {
        return trim(
            "{$this->nombre} {$this->apellido_paterno} " .
                ($this->apellido_materno ?? '')
        );
    }

    // ── Helpers de rol ──────────────────────────────────────────

    public function hasRole(string $slug): bool
    {
        return $this->role?->slug === $slug;
    }

    public function hasAnyRole(array $slugs): bool
    {
        return in_array($this->role?->slug, $slugs);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isClient(): bool
    {
        return $this->hasRole('client');
    }
}
