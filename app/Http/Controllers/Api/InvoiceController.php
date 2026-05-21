<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\TaxProfile;
use App\Models\Ticket;
use App\Services\FacturapiService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class InvoiceController extends Controller
{
    public function __construct(
        private FacturapiService $facturapi,
        private NotificationService $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 50);
        return response()->json(
            Invoice::with(['ticket:id,folio,fecha_ticket,tipo_combustible,monto', 'taxProfile:id,alias,rfc,razon_social'])
                ->where('user_id', $request->user()->id)
                ->orderByDesc('created_at')
                ->paginate($perPage),
        );
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeOwnership($request, $invoice);
        $invoice->load(['ticket:id,folio,fecha_ticket,tipo_combustible,monto', 'taxProfile']);
        return response()->json($invoice);
    }

    /**
     * Solicita la emisión de una factura para un ticket aprobado, usando
     * el tax_profile elegido. 1:1 con el ticket (índice único).
     */
    public function request(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'tax_profile_id' => ['required', 'integer', 'exists:tax_profiles,id'],
            'uso_cfdi' => ['nullable', 'string', 'max:5'],
            'payment_form' => ['nullable', 'string', 'max:5'],
        ]);

        $ticket = Ticket::findOrFail($data['ticket_id']);
        if ($ticket->user_id !== $request->user()->id) abort(404);
        if ($ticket->estado !== 'aprobado') {
            return response()->json(['message' => 'Solo se pueden facturar tickets aprobados.'], 422);
        }
        if (Invoice::where('ticket_id', $ticket->id)->exists()) {
            return response()->json(['message' => 'Este ticket ya tiene una factura asociada.'], 422);
        }

        // Ventana SAT: misma mes calendario. Se puede desactivar via setting
        // global `invoicing_strict_window` para hacer pruebas con tickets viejos.
        $strict = filter_var(
            \App\Models\ProgramSetting::getValue('invoicing_strict_window', 'true'),
            FILTER_VALIDATE_BOOLEAN,
        );
        if ($strict && ! $this->dentroDeVentanaSat($ticket->fecha_ticket)) {
            return response()->json([
                'message' => 'Este ticket está fuera de la ventana de facturación del mes correspondiente.',
            ], 422);
        }

        $profile = TaxProfile::findOrFail($data['tax_profile_id']);
        if ($profile->user_id !== $request->user()->id) abort(404);

        $invoice = Invoice::create([
            'user_id' => $request->user()->id,
            'ticket_id' => $ticket->id,
            'tax_profile_id' => $profile->id,
            'estado' => 'requested',
            'monto_total' => $ticket->monto,
            'uso_cfdi' => $data['uso_cfdi'] ?? $profile->uso_cfdi_default,
            'payment_form' => $data['payment_form'] ?? '99',
        ]);

        try {
            $invoice = $this->facturapi->issueInvoiceForTicket($invoice, $ticket, $profile);
        } catch (RuntimeException $e) {
            return response()->json([
                'data' => $invoice->fresh(),
                'message' => $e->getMessage(),
            ], 422);
        }

        $this->notifications->notify(
            $invoice->user_id,
            'invoice_generated',
            'Factura lista',
            "Tu factura del folio {$ticket->folio} ya está disponible para descargar.",
            [
                'icon' => 'document-text',
                'deeplink' => "fullok://invoices/{$invoice->id}",
                'prioridad' => 'medium',
                'payload' => ['invoice_id' => $invoice->id],
            ],
        );

        return response()->json($invoice, 201);
    }

    public function cancel(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeOwnership($request, $invoice);
        if ($invoice->estado !== 'generated') {
            return response()->json([
                'message' => 'Solo se pueden cancelar facturas en estado generado.',
            ], 422);
        }

        try {
            $invoice = $this->facturapi->cancel($invoice, $request->input('motivo', '02'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json($invoice);
    }

    /**
     * Proxy de descarga: pega a Facturapi con el API key del backend
     * y devuelve el binario directo al cliente. Esto evita exponer
     * URLs firmadas o el key al frontend.
     */
    public function download(Request $request, Invoice $invoice, string $kind)
    {
        $this->authorizeOwnership($request, $invoice);
        if ($invoice->estado !== 'generated' || ! $invoice->facturapi_invoice_id) {
            abort(404);
        }
        if (! in_array($kind, ['pdf', 'xml'], true)) abort(404);

        $bin = $kind === 'pdf'
            ? $this->facturapi->downloadPdf($invoice->facturapi_invoice_id)
            : $this->facturapi->downloadXml($invoice->facturapi_invoice_id);

        $mime = $kind === 'pdf' ? 'application/pdf' : 'application/xml';
        $name = ($invoice->folio_fiscal_uuid ?? $invoice->id) . '.' . $kind;

        return response($bin, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
    }

    /**
     * El SAT exige que el CFDI se emita dentro del mes calendario en que
     * ocurrió la operación. Margen: hasta el último día del mes siguiente
     * por seguridad (la regla exacta es "antes del corte mensual del PAC",
     * pero las dejamos a discreción del operador).
     */
    private function dentroDeVentanaSat(?string $fechaTicket): bool
    {
        if (! $fechaTicket) return false;
        $fecha = \Carbon\Carbon::parse($fechaTicket);
        // Permitir mismo mes + mes siguiente
        $limite = $fecha->copy()->addMonth()->endOfMonth();
        return now()->lte($limite);
    }

    private function authorizeOwnership(Request $request, Invoice $invoice): void
    {
        if ($invoice->user_id !== $request->user()->id) abort(404);
    }
}
