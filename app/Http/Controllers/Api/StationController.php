<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Station::query()->orderBy('nombre');
        if ($request->boolean('only_active')) {
            $query->where('activa', true);
        }
        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $station = Station::create($data);
        return response()->json($station, 201);
    }

    public function show(Station $station): JsonResponse
    {
        return response()->json($station);
    }

    public function update(Request $request, Station $station): JsonResponse
    {
        $data = $this->validateData($request);
        $station->update($data);
        return response()->json($station);
    }

    public function destroy(Station $station): JsonResponse
    {
        $station->delete();
        return response()->json(['ok' => true]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'direccion' => ['required', 'string', 'max:255'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'activa' => ['sometimes', 'boolean'],
        ]);
    }
}
