<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'nombre'      => 'Administrador',
                'slug'        => 'admin',
                'descripcion' => 'Acceso total al sistema',
            ],
            [
                'nombre'      => 'Cliente',
                'slug'        => 'client',
                'descripcion' => 'Cliente de la app Fullok Wallet',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }
    }
}