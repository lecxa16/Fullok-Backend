<?php

namespace Database\Seeders;

use App\Models\Reward;
use Illuminate\Database\Seeder;

class RewardsSeeder extends Seeder
{
    public function run(): void
    {
        // Catálogo inicial: productos físicos coleccionables del Mundial 2026.
        // Costos calibrados con un valor de punto de $0.20 MXN (config por defecto).
        $rewards = [
            [
                'nombre' => 'Llavero oficial Mundial 2026',
                'descripcion' => 'Llavero metálico con balón oficial del Mundial 2026. Acero inoxidable, 4 cm. Coleccionable.',
                'costo_puntos' => 400,
                'tipo' => 'producto',
                'imagen_url' => 'https://placehold.co/800x800/635BFF/FFFFFF/png?text=Llavero%0AMundial+2026&font=oswald',
                'stock' => 1000,
                'activo' => true,
            ],
            [
                'nombre' => 'Banderín Mundial 2026',
                'descripcion' => 'Banderín de tela con escudo oficial bordado. Cordón colgante. Edición coleccionable de aficionado.',
                'costo_puntos' => 600,
                'tipo' => 'producto',
                'imagen_url' => 'https://placehold.co/800x800/DF1B41/FFFFFF/png?text=Bander%C3%ADn%0AMundial+2026&font=oswald',
                'stock' => 500,
                'activo' => true,
            ],
            [
                'nombre' => 'Gorra Mundial 2026',
                'descripcion' => 'Gorra ajustable estilo trucker con bordado oficial del Mundial 2026. Talla única, ajuste de broche trasero.',
                'costo_puntos' => 1200,
                'tipo' => 'producto',
                'imagen_url' => 'https://placehold.co/800x800/0A2540/FFFFFF/png?text=Gorra%0AMundial+2026&font=oswald',
                'stock' => 300,
                'activo' => true,
            ],
            [
                'nombre' => 'Termo Mundial 2026',
                'descripcion' => 'Termo de acero inoxidable de 600 ml con grabado láser del Mundial 2026. Mantiene bebidas frías 24h y calientes 12h.',
                'costo_puntos' => 2000,
                'tipo' => 'producto',
                'imagen_url' => 'https://placehold.co/800x800/697386/FFFFFF/png?text=Termo%0AMundial+2026&font=oswald',
                'stock' => 200,
                'activo' => true,
            ],
            [
                'nombre' => 'Playera oficial Mundial 2026',
                'descripcion' => 'Playera oficial de aficionado, edición Mundial 2026. 100% poliéster transpirable. Tallas S, M, L, XL.',
                'costo_puntos' => 2500,
                'tipo' => 'producto',
                'imagen_url' => 'https://placehold.co/800x800/7A73FF/FFFFFF/png?text=Playera%0AMundial+2026&font=oswald',
                'stock' => 150,
                'activo' => true,
            ],
            [
                'nombre' => 'Balón oficial Mundial 2026',
                'descripcion' => 'Balón de futbol réplica oficial del Mundial 2026, talla #5. Cuero sintético de alta resistencia. Apto exterior e interior.',
                'costo_puntos' => 3500,
                'tipo' => 'producto',
                'imagen_url' => 'https://placehold.co/800x800/0A2540/FFBB00/png?text=Bal%C3%B3n%0AMundial+2026&font=oswald',
                'stock' => 50,
                'activo' => true,
            ],
        ];

        foreach ($rewards as $r) {
            Reward::updateOrCreate(['nombre' => $r['nombre']], $r);
        }
    }
}
