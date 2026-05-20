<?php

namespace App\Services;

use App\Models\PushToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envío de push notifications via Expo Push API.
 * https://docs.expo.dev/push-notifications/sending-notifications/
 *
 * Notas:
 * - Expo acepta hasta 100 mensajes por request.
 * - Tokens inválidos retornan "DeviceNotRegistered" → los borramos.
 * - Por simplicidad, este servicio es síncrono. Si el volumen crece,
 *   moverlo a un job en cola.
 */
class ExpoPushService
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';
    private const BATCH_SIZE = 100;

    /**
     * Envía a una lista de Expo push tokens.
     *
     * @param string[]              $tokens
     * @param array{title:string,body:string,data?:array,priority?:string,sound?:string} $payload
     * @return int Cantidad de mensajes enviados (no necesariamente entregados).
     */
    public function sendMany(array $tokens, array $payload): int
    {
        $valid = array_values(array_filter($tokens, fn ($t) => $this->isExpoToken($t)));
        if (empty($valid)) {
            return 0;
        }

        $total = 0;
        foreach (array_chunk($valid, self::BATCH_SIZE) as $batch) {
            $messages = array_map(fn ($token) => array_merge(
                ['to' => $token, 'sound' => 'default'],
                $payload,
            ), $batch);

            try {
                $res = Http::acceptJson()
                    ->asJson()
                    ->timeout(10)
                    ->post(self::ENDPOINT, $messages);

                if (! $res->ok()) {
                    Log::warning('Expo push HTTP error', [
                        'status' => $res->status(),
                        'body' => $res->body(),
                    ]);
                    continue;
                }

                $this->handleTicketResponse($batch, $res->json('data') ?? []);
                $total += count($batch);
            } catch (\Throwable $e) {
                Log::warning('Expo push exception', ['error' => $e->getMessage()]);
            }
        }
        return $total;
    }

    /**
     * Envía a un user específico, leyendo todos sus push tokens.
     */
    public function sendToUser(int $userId, array $payload): int
    {
        $tokens = PushToken::where('user_id', $userId)->pluck('token')->all();
        return $this->sendMany($tokens, $payload);
    }

    private function isExpoToken(string $t): bool
    {
        return str_starts_with($t, 'ExponentPushToken[') || str_starts_with($t, 'ExpoPushToken[');
    }

    /**
     * Inspecciona los tickets devueltos por Expo. Si un token está muerto
     * (DeviceNotRegistered), lo borramos para no seguir intentando.
     */
    private function handleTicketResponse(array $tokens, array $tickets): void
    {
        foreach ($tickets as $i => $ticket) {
            $status = $ticket['status'] ?? null;
            if ($status === 'error') {
                $errType = $ticket['details']['error'] ?? null;
                if ($errType === 'DeviceNotRegistered' && isset($tokens[$i])) {
                    PushToken::where('token', $tokens[$i])->delete();
                }
            }
        }
    }
}
