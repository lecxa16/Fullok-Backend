<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimulatorScenario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SimulatorScenarioController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => SimulatorScenario::orderByDesc('updated_at')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $data['created_by'] = $request->user()->id;
        $scenario = SimulatorScenario::create($data);
        return response()->json($scenario, 201);
    }

    public function update(Request $request, SimulatorScenario $scenario): JsonResponse
    {
        $data = $this->validateData($request);
        $scenario->update($data);
        return response()->json($scenario);
    }

    public function destroy(SimulatorScenario $scenario): JsonResponse
    {
        $scenario->delete();
        return response()->json(['ok' => true]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'supuestos' => ['required', 'array'],
            'supuestos.clientes' => ['required', 'numeric', 'min:0'],
            'supuestos.frecuencia' => ['required', 'numeric', 'min:0'],
            'supuestos.ticket_promedio' => ['required', 'numeric', 'min:0'],
            'supuestos.breakage' => ['required', 'numeric', 'min:0', 'max:1'],
            'supuestos.multiplicador_efectivo' => ['required', 'numeric', 'min:1'],
        ]);
    }
}
