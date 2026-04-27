<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::with('role')->orderBy('name')->get();
        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
            ],
            'role_id' => 'required|exists:roles,id',
            'activo' => 'boolean',
        ], [
            'password.regex' => 'La contraseña debe tener al menos 1 mayúscula, 1 minúscula y 1 número.',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['activo'] = $validated['activo'] ?? true;

        $user = User::create($validated);
        $user->load('role');

        return response()->json($user, 201);
    }

    public function show(User $usuario): JsonResponse
    {
        $usuario->load('role');
        return response()->json($usuario);
    }

    public function update(Request $request, User $usuario): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($usuario->id)],
            'password' => [
                'sometimes',
                'nullable',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
            ],
            'role_id' => 'sometimes|required|exists:roles,id',
            'activo' => 'sometimes|boolean',
        ], [
            'password.regex' => 'La contraseña debe tener al menos 1 mayúscula, 1 minúscula y 1 número.',
        ]);

        if (isset($validated['password']) && $validated['password']) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $usuario->update($validated);
        $usuario->load('role');

        return response()->json($usuario);
    }

    public function destroy(User $usuario): JsonResponse
    {
        // Prevent deleting yourself
        if (auth()->id() === $usuario->id) {
            return response()->json(['message' => 'No puedes eliminar tu propio usuario'], 403);
        }

        try {
            $usuario->delete();
            return response()->json(null, 204);
        } catch (\Illuminate\Database\QueryException $e) {
            // Foreign key constraint — user has related records
            return response()->json([
                'message' => 'No se puede eliminar este usuario porque tiene registros asociados. Puedes desactivarlo en su lugar.'
            ], 422);
        }
    }

    public function roles(): JsonResponse
    {
        $roles = Role::orderBy('nombre')->get();
        return response()->json($roles);
    }
}
