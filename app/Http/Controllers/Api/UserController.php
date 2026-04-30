<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // Lista todos los usuarios — solo admin
    public function index(): JsonResponse
    {
        $users = User::with('role')
            ->orderBy('apellido_paterno')
            ->orderBy('nombre')
            ->get();

        return response()->json($users);
    }

    // Ver un usuario — solo admin
    public function show(User $usuario): JsonResponse
    {
        return response()->json($usuario);
    }

    // Editar usuario — solo admin
    public function update(Request $request, User $usuario): JsonResponse
    {
        $validated = $request->validate([
            'nombre'           => 'sometimes|required|string|max:100',
            'apellido_paterno' => 'sometimes|required|string|max:100',
            'apellido_materno' => 'sometimes|nullable|string|max:100',
            'telefono'         => 'sometimes|nullable|string|max:20',
            'email'            => [
                'sometimes',
                'required',
                'email',
                Rule::unique('users')->ignore($usuario->id)
            ],
            'genero'           => 'sometimes|in:masculino,femenino,otro,prefiero_no_decir',
            'fecha_nacimiento' => 'sometimes|date|before:today',
            'role_id'          => 'sometimes|required|exists:roles,id',
            'activo'           => 'sometimes|boolean',
            'password'         => 'sometimes|nullable|string|min:6',
        ]);

        if (isset($validated['password']) && $validated['password']) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $usuario->update($validated);

        return response()->json($usuario->fresh());
    }

    // Eliminar usuario — solo admin
    public function destroy(User $usuario): JsonResponse
    {
        if (auth()->id() === $usuario->id) {
            return response()->json([
                'message' => 'No puedes eliminarte a ti mismo.',
            ], 403);
        }

        try {
            $usuario->delete();
            return response()->json(null, 204);
        } catch (\Illuminate\Database\QueryException) {
            return response()->json([
                'message' => 'No se puede eliminar, tiene registros asociados. Desactívalo en su lugar.',
            ], 422);
        }
    }

    // Lista de roles — para formularios del admin
    public function roles(): JsonResponse
    {
        return response()->json(Role::orderBy('nombre')->get());
    }
}
