<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaxProfile;
use App\Services\FacturapiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TaxProfileController extends Controller
{
    public function __construct(private FacturapiService $facturapi) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => TaxProfile::where('user_id', $request->user()->id)
                ->orderByDesc('is_default')
                ->orderBy('alias')
                ->get(),
        ]);
    }

    public function show(Request $request, TaxProfile $taxProfile): JsonResponse
    {
        $this->authorizeOwnership($request, $taxProfile);
        return response()->json($taxProfile);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $data['user_id'] = $request->user()->id;

        $profile = DB::transaction(function () use ($data, $request) {
            if (! empty($data['is_default'])) {
                TaxProfile::where('user_id', $request->user()->id)
                    ->update(['is_default' => false]);
            }
            return TaxProfile::create($data);
        });

        // Intentar registrar en Facturapi. Si falla, el perfil queda creado
        // pero sin facturapi_customer_id — se resincroniza la próxima vez.
        try {
            if ($this->facturapi->isConfigured()) {
                $profile = $this->facturapi->syncCustomer($profile);
            }
        } catch (RuntimeException $e) {
            return response()->json([
                'data' => $profile,
                'warning' => 'Perfil guardado, pero Facturapi rechazó la validación: ' . $e->getMessage(),
            ], 201);
        }

        return response()->json($profile, 201);
    }

    public function update(Request $request, TaxProfile $taxProfile): JsonResponse
    {
        $this->authorizeOwnership($request, $taxProfile);
        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $request, $taxProfile) {
            if (! empty($data['is_default'])) {
                TaxProfile::where('user_id', $request->user()->id)
                    ->where('id', '!=', $taxProfile->id)
                    ->update(['is_default' => false]);
            }
            $taxProfile->update($data);
        });

        try {
            if ($this->facturapi->isConfigured()) {
                $taxProfile = $this->facturapi->syncCustomer($taxProfile->fresh());
            }
        } catch (RuntimeException $e) {
            return response()->json([
                'data' => $taxProfile->fresh(),
                'warning' => 'Cambios guardados, pero Facturapi rechazó la actualización: ' . $e->getMessage(),
            ]);
        }

        return response()->json($taxProfile->fresh());
    }

    public function destroy(Request $request, TaxProfile $taxProfile): JsonResponse
    {
        $this->authorizeOwnership($request, $taxProfile);

        try {
            if ($this->facturapi->isConfigured()) {
                $this->facturapi->deleteCustomer($taxProfile);
            }
        } catch (RuntimeException $e) {
            // ignoramos errores del PAC al borrar — borramos localmente igual
        }

        $taxProfile->delete();
        return response()->json(['ok' => true]);
    }

    private function authorizeOwnership(Request $request, TaxProfile $taxProfile): void
    {
        if ($taxProfile->user_id !== $request->user()->id) {
            abort(404);
        }
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'rfc' => ['required', 'string', 'min:12', 'max:13'],
            'razon_social' => ['required', 'string', 'max:200'],
            'regimen_fiscal_sat' => ['required', 'string', 'max:5'],
            'uso_cfdi_default' => ['nullable', 'string', 'max:5'],
            'cp_fiscal' => ['required', 'string', 'size:5'],
            'email_facturacion' => ['required', 'email', 'max:200'],
            'alias' => ['nullable', 'string', 'max:60'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }
}
