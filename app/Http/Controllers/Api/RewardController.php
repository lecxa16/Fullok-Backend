<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RewardController extends Controller
{
    /**
     * Catálogo. Si llega ?available=1 (o cliente sin admin), filtra solo disponibles.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Reward::query()->orderBy('costo_puntos');
        $user = $request->user();
        $isAdmin = $user && $user->hasRole('admin');

        if (! $isAdmin || $request->boolean('available')) {
            $query->available();
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $reward = Reward::create($this->validateData($request));
        return response()->json($reward, 201);
    }

    public function show(Reward $reward): JsonResponse
    {
        return response()->json($reward);
    }

    public function update(Request $request, Reward $reward): JsonResponse
    {
        $reward->update($this->validateData($request));
        return response()->json($reward);
    }

    public function destroy(Reward $reward): JsonResponse
    {
        if ($reward->imagen_path) {
            Storage::disk('public')->delete($reward->imagen_path);
        }
        $reward->delete();
        return response()->json(['ok' => true]);
    }

    public function uploadImage(Request $request, Reward $reward): JsonResponse
    {
        $request->validate([
            'imagen' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // 5 MB
        ]);

        // Borrar imagen anterior (si existía) para no acumular basura
        if ($reward->imagen_path) {
            Storage::disk('public')->delete($reward->imagen_path);
        }

        $path = $request->file('imagen')->store('rewards/' . $reward->id, 'public');
        $reward->update(['imagen_path' => $path]);

        return response()->json($reward->fresh());
    }

    public function deleteImage(Reward $reward): JsonResponse
    {
        if ($reward->imagen_path) {
            Storage::disk('public')->delete($reward->imagen_path);
            $reward->update(['imagen_path' => null]);
        }
        return response()->json($reward->fresh());
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'costo_puntos' => ['required', 'integer', 'min:1'],
            'tipo' => ['required', 'in:descuento,combustible,servicio,producto,otro'],
            'imagen_url' => ['nullable', 'string', 'max:500'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'inicia_at' => ['nullable', 'date'],
            'termina_at' => ['nullable', 'date', 'after_or_equal:inicia_at'],
            'activo' => ['sometimes', 'boolean'],
        ]);
    }
}
