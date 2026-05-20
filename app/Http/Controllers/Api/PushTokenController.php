<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'    => ['required', 'string', 'max:200'],
            'platform' => ['required', 'in:ios,android'],
        ]);

        // Si el token ya existe (mismo device), actualizamos el user_id y refrescamos
        // last_seen. Esto cubre el caso de logout/login con distintos usuarios.
        $row = PushToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id'      => $request->user()->id,
                'platform'     => $data['platform'],
                'last_seen_at' => now(),
            ],
        );

        return response()->json($row, 201);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        PushToken::where('token', $token)
            ->where('user_id', $request->user()->id)
            ->delete();
        return response()->json(['ok' => true]);
    }
}
