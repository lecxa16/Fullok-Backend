<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // ── Login ──────────────────────────────────────────────────

    public function login(Request $request): JsonResponse
    {
        $key = 'login_' . $request->ip();
        if (Cache::get($key, 0) >= 5) {
            return response()->json([
                'message' => 'Demasiados intentos. Espera 1 minuto.',
            ], 429);
        }

        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! password_verify($request->password, $user->password)) {
            Cache::put($key, Cache::get($key, 0) + 1, 60);
            throw ValidationException::withMessages([
                'email' => ['Las credenciales son incorrectas.'],
            ]);
        }

        if (! $user->activo) {
            throw ValidationException::withMessages([
                'email' => ['Tu cuenta está desactivada. Contacta al administrador.'],
            ]);
        }

        Cache::forget($key);

        // Soportamos múltiples sesiones por usuario (admin web + app móvil + ...).
        // El nombre del token permite identificar el origen y borrar el más viejo
        // del mismo origen para evitar acumulación infinita.
        $tokenName = $request->input('device_name', 'auth-token');
        $user->tokens()->where('name', $tokenName)->delete();
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    // ── Register ───────────────────────────────────────────────

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'nombre'           => 'required|string|max:100',
            'apellido_paterno' => 'required|string|max:100',
            'apellido_materno' => 'nullable|string|max:100',
            'telefono'         => 'required|string|min:10|max:20',
            'email'            => 'required|email|unique:users,email',
            'password'         => 'required|string|min:6|confirmed',
            'genero'           => 'required|in:masculino,femenino,otro,prefiero_no_decir',
            'fecha_nacimiento' => 'required|date|before:today',
        ]);

        $userRole = Role::where('slug', 'user')->first();

        $user = User::create([
            'role_id'          => $userRole?->id,
            'nombre'           => $request->nombre,
            'apellido_paterno' => $request->apellido_paterno,
            'apellido_materno' => $request->apellido_materno,
            'email'            => strtolower($request->email),
            'telefono'         => $request->telefono,
            'password'         => $request->password,
            'genero'           => $request->genero,
            'fecha_nacimiento' => $request->fecha_nacimiento,
            'activo'           => true,
            'role_id'          => 2,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 201);
    }

    // ── Logout ─────────────────────────────────────────────────

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    // ── Me ─────────────────────────────────────────────────────

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    // ── Forgot password ────────────────────────────────────────

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        // Siempre 200 — no revelamos si el email existe o no
        if (!$user) {
            return response()->json([
                'message' => 'No encontramos una cuenta con ese correo.'
            ], 404);
        }

        // Invalidar códigos anteriores no usados
        PasswordResetCode::where('email', $request->email)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        // Código de 6 dígitos con ceros a la izquierda si aplica
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PasswordResetCode::create([
            'email'      => $request->email,
            'code'       => $code,
            'expires_at' => now()->addMinutes(15),
        ]);

        $html = "
            <!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Código de recuperación</title>
            </head>
            <body style='margin:0; padding:0; background-color:#f5f5f5; font-family: Arial, sans-serif;'>
                <table width='100%' cellpadding='0' cellspacing='0' style='background-color:#f5f5f5; padding: 40px 0;'>
                    <tr>
                        <td align='center'>
                            <table width='520' cellpadding='0' cellspacing='0' style='background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08);'>

                                <!-- Header rojo -->
                                <tr>
                                    <td align='center' style='background-color:#E31E24; padding: 36px 40px 28px;'>
                                        <p style='margin:0; font-size:11px; font-weight:700; color:rgba(255,255,255,0.7); letter-spacing:6px; text-transform:uppercase;'>WALLET</p>
                                        <p style='margin:6px 0 0; font-size:28px; font-weight:900; color:#ffffff; letter-spacing:-0.5px;'>Fullok</p>
                                    </td>
                                </tr>

                                <!-- Cuerpo -->
                                <tr>
                                    <td style='padding: 40px 40px 20px; text-align:center;'>
                                        <p style='margin:0 0 8px; font-size:22px; font-weight:800; color:#1a1a1a;'>Recupera tu contraseña</p>
                                        <p style='margin:0 0 28px; font-size:15px; color:#666666; line-height:1.6;'>
                                            Recibimos una solicitud para restablecer la contraseña de tu cuenta Fullok Wallet. Usa el siguiente código:
                                        </p>

                                        <!-- Código -->
                                        <table width='100%' cellpadding='0' cellspacing='0'>
                                            <tr>
                                                <td align='center'>
                                                    <div style='background-color:#fff5f5; border: 2px solid #E31E24; border-radius:12px; padding: 24px 40px; display:inline-block;'>
                                                        <p style='margin:0 0 4px; font-size:11px; font-weight:700; color:#E31E24; letter-spacing:3px; text-transform:uppercase;'>Tu código</p>
                                                        <p style='margin:0; font-size:42px; font-weight:900; color:#E31E24; letter-spacing:10px;'>{$code}</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>

                                        <p style='margin:28px 0 0; font-size:14px; color:#888888; text-align:center;'>
                                            ⏱ Este código vence en <strong>15 minutos</strong>
                                        </p>
                                    </td>
                                </tr>

                                <!-- Divider -->
                                <tr>
                                    <td style='padding: 0 40px;'>
                                        <hr style='border:none; border-top:1px solid #f0f0f0; margin:0;'>
                                    </td>
                                </tr>

                                <!-- Aviso -->
                                <tr>
                                    <td style='padding: 20px 40px 36px; text-align:center;'>
                                        <p style='margin:0; font-size:13px; color:#aaaaaa; line-height:1.6;'>
                                            Si no solicitaste este código, puedes ignorar este correo. Tu cuenta sigue segura.
                                        </p>
                                    </td>
                                </tr>

                                <!-- Footer -->
                                <tr>
                                    <td align='center' style='background-color:#fafafa; padding: 20px 40px; border-top: 1px solid #f0f0f0;'>
                                        <p style='margin:0; font-size:12px; color:#cccccc;'>© 2026 Fullok Wallet · Todos los derechos reservados</p>
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            ";

            Mail::html($html, fn($m) => $m
                ->to($request->email)
                ->subject('🔐 Tu código de recuperación — Fullok Wallet')
            );

        return response()->json(['message' => 'Código enviado a tu correo.']);
    }

    // ── Verify code ────────────────────────────────────────────

    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);

        $reset = PasswordResetCode::where('email', $request->email)
            ->where('code', $request->code)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $reset) {
            return response()->json([
                'message' => 'Código inválido o expirado.',
            ], 422);
        }

        return response()->json(['message' => 'Código válido.', 'valid' => true]);
    }

    // ── Reset password ─────────────────────────────────────────

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'code'     => 'required|string|size:6',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $reset = PasswordResetCode::where('email', $request->email)
            ->where('code', $request->code)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $reset) {
            return response()->json([
                'message' => 'Código inválido o expirado.',
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        if (! $user) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        $user->update(['password' => $request->password]);
        $reset->update(['used_at' => now()]);

        // Revocar todos los tokens por seguridad
        $user->tokens()->delete();

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }
}