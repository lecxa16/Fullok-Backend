<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Rate limit: 5 login attempts per minute per IP
        $key = 'login_' . request()->ip();
        if (Cache::get($key, 0) >= 5) {
            return response()->json([
                'message' => 'Demasiados intentos de inicio de sesión. Espera 1 minuto.'
            ], 429);
        }

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            Cache::put($key, Cache::get($key, 0) + 1, 60); // expires in 60 seconds
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        if (! $user->activo) {
            Cache::put($key, Cache::get($key, 0) + 1, 60); // expires in 60 seconds
            throw ValidationException::withMessages([
                'email' => ['Tu cuenta ha sido desactivada. Contacta al administrador.'],
            ]);
        }

        Cache::forget($key);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}
