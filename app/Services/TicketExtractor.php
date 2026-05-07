<?php

namespace App\Services;

use App\Models\Station;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Extrae datos estructurados de un ticket de gasolina usando Claude Vision.
 *
 * Diseño:
 * - Usa el endpoint /v1/messages de Anthropic con tool_use forzado para garantizar
 *   un JSON con schema estricto.
 * - El system prompt incluye la lista de estaciones Fullok activas para que el
 *   modelo pueda matchear el nombre legible al `station_id` real.
 * - Imágenes en base64 inline (las imágenes son <5MB, dentro del límite de Anthropic).
 */
class TicketExtractor
{
    public function extract(UploadedFile $foto): array
    {
        $apiKey = config('services.anthropic.api_key');
        if (! $apiKey) {
            throw new RuntimeException(
                'ANTHROPIC_API_KEY no está configurado. Agrégalo a .env y reinicia.',
            );
        }

        $base64 = base64_encode(file_get_contents($foto->getRealPath()));
        $mediaType = $foto->getMimeType() ?: 'image/jpeg';

        $stationsHint = Station::where('activa', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn ($s) => "  - id={$s->id} {$s->nombre}")
            ->join("\n");

        $systemPrompt = <<<PROMPT
            Eres un asistente que extrae datos estructurados de tickets de gasolina mexicanos.

            Lee el ticket y devuelve los campos solicitados. Reglas:

            - "folio" es el número de venta o transacción en el ticket. NO incluyas prefijos largos (FOLIO:, TICKET:). Solo el identificador.
            - "fecha" en formato YYYY-MM-DD. **MUY IMPORTANTE**: los tickets son MEXICANOS y usan formato DD/MM/YYYY (día/mes/año), NUNCA MM/DD/YYYY. Por ejemplo "06/05/2026" significa 6 de mayo de 2026 → "2026-05-06". Otra pista: si el primer número es ≤ 12 y el segundo > 12, podría ser MM/DD; pero si AMBOS son ≤ 12, ASUME DD/MM (mexicano).
            - "monto" es el total a pagar en pesos (number). Sin símbolo de moneda.
            - "litros" es la cantidad despachada (number).
            - "tipo_combustible" debe ser exactamente "magna", "premium" o "diesel" (lowercase). En tickets mexicanos: Verde/Regular = "magna"; Roja/Premium = "premium"; Diésel = "diesel".
            - "station_id" es el id NUMÉRICO de la estación Fullok. Mira el nombre/dirección que aparezca en el ticket y matchéalo con esta lista de estaciones activas:

            {$stationsHint}

              Si no puedes determinarla con confianza, devuelve null.
            - "confianza" es tu auto-evaluación de qué tan legible y confiable fue la extracción: "alta" (todo claro), "media" (algunos datos inferidos), "baja" (imagen poco legible).
            - Si un campo no es legible o no aparece, devuelve null para ese campo. NO inventes datos.

            Llama SIEMPRE a la herramienta `extract_ticket` con tu respuesta.
            PROMPT;

        $tool = [
            'name' => 'extract_ticket',
            'description' => 'Devuelve los datos estructurados del ticket de gasolina.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'folio' => ['type' => ['string', 'null']],
                    'fecha' => ['type' => ['string', 'null'], 'description' => 'YYYY-MM-DD'],
                    'monto' => ['type' => ['number', 'null']],
                    'litros' => ['type' => ['number', 'null']],
                    'tipo_combustible' => [
                        'type' => ['string', 'null'],
                        'enum' => ['magna', 'premium', 'diesel', null],
                    ],
                    'station_id' => ['type' => ['integer', 'null']],
                    'confianza' => ['type' => 'string', 'enum' => ['alta', 'media', 'baja']],
                ],
                'required' => ['confianza'],
            ],
        ];

        $payload = [
            'model' => config('services.anthropic.model'),
            'max_tokens' => 1024,
            'system' => [
                [
                    'type' => 'text',
                    'text' => $systemPrompt,
                    'cache_control' => ['type' => 'ephemeral'],
                ],
            ],
            'tools' => [$tool],
            'tool_choice' => ['type' => 'tool', 'name' => 'extract_ticket'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $mediaType,
                                'data' => $base64,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => 'Extrae los datos de este ticket de gasolina.',
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => config('services.anthropic.api_version'),
            'content-type' => 'application/json',
        ])
            ->timeout((int) config('services.anthropic.timeout'))
            ->post('https://api.anthropic.com/v1/messages', $payload);

        if (! $response->successful()) {
            Log::warning('Claude API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException(
                'No se pudo procesar el ticket en este momento. Intenta de nuevo o llena los datos manualmente.',
            );
        }

        $data = $response->json();
        $toolUse = collect($data['content'] ?? [])
            ->firstWhere('type', 'tool_use');

        if (! $toolUse || ! isset($toolUse['input'])) {
            throw new RuntimeException(
                'La IA no devolvió datos estructurados. Llena el ticket manualmente.',
            );
        }

        $extracted = $toolUse['input'];

        return [
            'folio' => $extracted['folio'] ?? null,
            'fecha_ticket' => $this->normalizeFecha($extracted['fecha'] ?? null),
            'monto' => $this->normalizeNumber($extracted['monto'] ?? null),
            'litros' => $this->normalizeNumber($extracted['litros'] ?? null),
            'tipo_combustible' => $extracted['tipo_combustible'] ?? null,
            'station_id' => isset($extracted['station_id']) && is_numeric($extracted['station_id'])
                ? (int) $extracted['station_id']
                : null,
            'confianza' => $extracted['confianza'] ?? 'baja',
            'usage' => [
                'input_tokens' => $data['usage']['input_tokens'] ?? null,
                'output_tokens' => $data['usage']['output_tokens'] ?? null,
                'cache_creation_input_tokens' => $data['usage']['cache_creation_input_tokens'] ?? null,
                'cache_read_input_tokens' => $data['usage']['cache_read_input_tokens'] ?? null,
            ],
        ];
    }

    private function normalizeFecha(?string $f): ?string
    {
        if (! $f) return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)) return $f;
        try {
            return \Carbon\Carbon::parse($f)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeNumber(mixed $v): ?float
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) return (float) $v;
        return null;
    }
}
