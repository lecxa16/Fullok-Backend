<?php

namespace Database\Seeders;

use App\Models\Station;
use Illuminate\Database\Seeder;

class StationsSeeder extends Seeder
{
    public function run(): void
    {
        // Estaciones reales de Fullok en Nuevo León (https://fullok.mx).
        // Coordenadas: geocoded vía Nominatim/OSM o aproximadas por colonia.
        // El admin puede ajustar lat/lng manualmente desde el CRUD.
        $stations = [
            [
                'nombre' => 'Fullok Obrera',
                'direccion' => 'Av. Francisco Márquez 823, Obrera, Monterrey, NL',
                'lat' => 25.6748680, 'lng' => -100.2898595,
            ],
            [
                'nombre' => 'Fullok Central',
                'direccion' => 'Rodrigo Gómez 1899, Central, Monterrey, NL',
                'lat' => 25.7184094, 'lng' => -100.3424159,
            ],
            [
                'nombre' => 'Fullok Centro Villarreal',
                'direccion' => 'Antonio I. Villarreal 3701A, Monterrey, NL',
                'lat' => 25.7035725, 'lng' => -100.2775054,
            ],
            [
                'nombre' => 'Fullok Independencia',
                'direccion' => 'Baja California 325B, Independencia, Monterrey, NL',
                'lat' => 25.6578587, 'lng' => -100.3088410,
            ],
            [
                'nombre' => 'Fullok Nor Pte',
                'direccion' => 'Benito Juárez 100, Infantería Monterreal, Monterrey, NL',
                'lat' => 25.7400000, 'lng' => -100.3300000,
            ],
            [
                'nombre' => 'Fullok Marín',
                'direccion' => 'Carr. Miguel Alemán Km 94, Ejido Marín, NL',
                'lat' => 25.8790000, 'lng' => -100.0410000,
            ],
            [
                'nombre' => 'Fullok Metroplex',
                'direccion' => 'Av. Concordia 200 A, Metroplex Apodaca, NL',
                'lat' => 25.7745000, 'lng' => -100.1640000,
            ],
            [
                'nombre' => 'Fullok Apodaca',
                'direccion' => 'Av. México 302, Apodaca, NL',
                'lat' => 25.7327214, 'lng' => -100.1925940,
            ],
            [
                'nombre' => 'Fullok Cumbres',
                'direccion' => 'Alejandro de Rodas 3102, Cumbres, Monterrey, NL',
                'lat' => 25.7275706, 'lng' => -100.3888009,
            ],
            [
                'nombre' => 'Fullok Ruiz Cortines',
                'direccion' => 'Prolongación Ruiz Cortines 6600A, Monterrey, NL',
                'lat' => 25.7587110, 'lng' => -100.4049915,
            ],
            [
                'nombre' => 'Fullok Escobedo',
                'direccion' => 'Av. Monterrey 101, Nueva Esperanza, Escobedo, NL',
                'lat' => 25.8021164, 'lng' => -100.3934081,
            ],
            [
                'nombre' => 'Fullok Santiago Centro',
                'direccion' => 'Carr. Nacional y Rayón Centro, Santiago, NL',
                'lat' => 25.4256000, 'lng' => -100.1409000,
            ],
            [
                'nombre' => 'Fullok Santiago Carretera',
                'direccion' => 'Carr. Nacional Km 245.712, Santiago, NL',
                'lat' => 25.4150000, 'lng' => -100.1350000,
            ],
            [
                'nombre' => 'Fullok Sur Pte Cuauhtémoc',
                'direccion' => 'Av. Cuauhtémoc 105, Cuauhtémoc, NL',
                'lat' => 25.7254367, 'lng' => -100.2928660,
            ],
        ];

        foreach ($stations as $s) {
            Station::updateOrCreate(
                ['nombre' => $s['nombre']],
                array_merge($s, ['activa' => true]),
            );
        }
    }
}
