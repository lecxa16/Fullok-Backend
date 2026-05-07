<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProgramSetting;
use App\Models\ProgramSettingHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgramSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = ProgramSetting::orderBy('group')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'key' => $r->key,
                'value' => $r->value,
                'typed_value' => $r->typed_value,
                'type' => $r->type,
                'group' => $r->group,
                'label' => $r->label,
                'description' => $r->description,
                'sort_order' => $r->sort_order,
                'updated_at' => $r->updated_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.key' => ['required', 'string', 'exists:program_settings,key'],
            'settings.*.value' => ['nullable'],
        ]);

        $userId = $request->user()->id;
        $changed = 0;

        DB::transaction(function () use ($data, $userId, &$changed) {
            foreach ($data['settings'] as $payload) {
                $row = ProgramSetting::where('key', $payload['key'])->lockForUpdate()->first();
                if (! $row) {
                    continue;
                }

                $newRaw = $this->normalizeIncoming($payload['value'] ?? null, $row->type);

                if ((string) $row->value === (string) $newRaw) {
                    continue;
                }

                ProgramSettingHistory::create([
                    'key' => $row->key,
                    'old_value' => $row->value,
                    'new_value' => $newRaw,
                    'changed_by' => $userId,
                    'changed_at' => now(),
                ]);

                $row->value = $newRaw;
                $row->updated_by = $userId;
                $row->save();
                $changed++;
            }
        });

        return response()->json([
            'changed' => $changed,
            'message' => $changed === 0 ? 'Sin cambios.' : "Se actualizaron {$changed} valores.",
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 30), 100);
        $rows = ProgramSettingHistory::with('changedBy:id,nombre,apellido_paterno,email')
            ->orderByDesc('changed_at')
            ->paginate($perPage);

        return response()->json($rows);
    }

    private function normalizeIncoming(mixed $value, string $type): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return match ($type) {
            'number' => (string) (0 + $value),
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'json' => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }
}
