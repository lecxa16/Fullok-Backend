<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();

        User::updateOrCreate(
            ['email' => 'admin@fullok.mx'],
            [
                'nombre'           => 'Admin',
                'apellido_paterno' => 'Fullok',
                'email'            => 'admin@fullok.mx',
                'password'         => bcrypt('Admin1234'),
                'role_id'          => $adminRole?->id,
                'activo'           => true,
            ]
        );
    }
}
