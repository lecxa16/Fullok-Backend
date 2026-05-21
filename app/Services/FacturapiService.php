<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\TaxProfile;
use App\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Wrapper de la API REST de Facturapi (https://docs.facturapi.io/).
 *
 * Distinguimos sandbox vs prod por el prefijo del API key:
 *   - sk_test_XXXX → sandbox (CFDI simulado, sin valor fiscal)
 *   - sk_user_XXXX → producción
 *
 * El API key se lee de config('services.facturapi.key') que mapea a
 * FACTURAPI_KEY del .env. Si no está configurada, los métodos lanzan
 * RuntimeException para que el caller pueda decidir si fallar o degradar.
 */
class FacturapiService
{
    private const BASE_URL = 'https://www.facturapi.io/v2';

    // SAT product keys para combustibles (CFDI 4.0)
    private const PRODUCT_KEYS = [
        'magna' => '15101515',
        'premium' => '15101514',
        'diesel' => '15101505',
    ];

    private const UNIT_KEY_LITER = 'LTR';
    private const TAX_RATE_IVA = 0.16;

    public function isConfigured(): bool
    {
        return ! empty($this->key());
    }

    public function isSandbox(): bool
    {
        return str_starts_with($this->key() ?? '', 'sk_test_');
    }

    /**
     * Crea o actualiza el customer en Facturapi para este tax profile.
     * Si ya tiene facturapi_customer_id, hace PUT; si no, POST.
     * Persiste el id en la tabla.
     */
    public function syncCustomer(TaxProfile $profile): TaxProfile
    {
        $payload = [
            'legal_name' => $profile->razon_social,
            'tax_id' => strtoupper($profile->rfc),
            'tax_system' => $profile->regimen_fiscal_sat,
            'email' => $profile->email_facturacion,
            'address' => [
                'zip' => $profile->cp_fiscal,
            ],
        ];

        if ($profile->facturapi_customer_id) {
            $res = $this->http()->put(
                self::BASE_URL . '/customers/' . $profile->facturapi_customer_id,
                $payload,
            );
        } else {
            $res = $this->http()->post(self::BASE_URL . '/customers', $payload);
        }

        if (! $res->ok()) {
            $msg = $res->json('message') ?? 'Error al sincronizar el cliente fiscal con Facturapi.';
            throw new RuntimeException($msg);
        }

        $profile->update([
            'facturapi_customer_id' => $res->json('id'),
            'validated_at' => now(),
        ]);

        return $profile->fresh();
    }

    public function deleteCustomer(TaxProfile $profile): void
    {
        if (! $profile->facturapi_customer_id) return;
        $this->http()->delete(self::BASE_URL . '/customers/' . $profile->facturapi_customer_id);
    }

    /**
     * Emite una factura por un ticket aprobado. Si la respuesta es exitosa,
     * marca la Invoice como `generated` y guarda folio, UUID y URLs.
     *
     * En sandbox el CFDI no tiene valor fiscal pero la estructura de la
     * respuesta es idéntica, así que el código no diferencia.
     */
    public function issueInvoiceForTicket(Invoice $invoice, Ticket $ticket, TaxProfile $profile): Invoice
    {
        if ($ticket->estado !== 'aprobado') {
            throw new RuntimeException('Solo se pueden facturar tickets aprobados.');
        }
        if (! $profile->facturapi_customer_id) {
            $profile = $this->syncCustomer($profile);
        }

        $productKey = self::PRODUCT_KEYS[$ticket->tipo_combustible] ?? '15101500';
        $invoice->update(['estado' => 'processing']);

        $payload = [
            'customer' => $profile->facturapi_customer_id,
            'items' => [[
                'quantity' => (float) $ticket->litros,
                'product' => [
                    'description' => "Carga de " . ucfirst($ticket->tipo_combustible)
                        . " — folio {$ticket->folio}",
                    'product_key' => $productKey,
                    'unit_key' => self::UNIT_KEY_LITER,
                    // Facturapi calcula precio unitario = total / quantity, así que
                    // pasamos el monto total como price total con tax_included = true
                    'price' => (float) $ticket->monto / max($ticket->litros, 0.01),
                    'tax_included' => true,
                    'taxes' => [[
                        'type' => 'IVA',
                        'rate' => self::TAX_RATE_IVA,
                    ]],
                ],
            ]],
            'use' => $invoice->uso_cfdi,
            'payment_form' => $invoice->payment_form,
            'payment_method' => 'PUE', // pago en una exhibición
        ];

        $res = $this->http()->post(self::BASE_URL . '/invoices', $payload);

        if (! $res->ok()) {
            $msg = $res->json('message') ?? 'Facturapi rechazó la emisión.';
            $invoice->update([
                'estado' => 'error',
                'error_message' => $msg,
                'pac_response' => $res->json(),
            ]);
            throw new RuntimeException($msg);
        }

        $data = $res->json();
        $invoice->update([
            'estado' => 'generated',
            'facturapi_invoice_id' => $data['id'] ?? null,
            'folio_fiscal_uuid' => $data['uuid'] ?? null,
            'serie' => $data['series'] ?? null,
            'folio' => isset($data['folio_number']) ? (string) $data['folio_number'] : null,
            'pdf_url' => self::BASE_URL . '/invoices/' . ($data['id'] ?? '') . '/pdf',
            'xml_url' => self::BASE_URL . '/invoices/' . ($data['id'] ?? '') . '/xml',
            'emitida_at' => now(),
            'pac_response' => $data,
        ]);

        return $invoice->fresh();
    }

    public function cancel(Invoice $invoice, string $motivo = '02'): Invoice
    {
        if (! $invoice->facturapi_invoice_id) {
            throw new RuntimeException('Esta factura no tiene id de Facturapi para cancelar.');
        }
        $res = $this->http()->delete(
            self::BASE_URL . '/invoices/' . $invoice->facturapi_invoice_id . '?motive=' . $motivo,
        );
        if (! $res->ok()) {
            $msg = $res->json('message') ?? 'No se pudo cancelar la factura.';
            throw new RuntimeException($msg);
        }
        $invoice->update([
            'estado' => 'cancelled',
            'cancelada_at' => now(),
            'motivo_cancelacion' => $motivo,
        ]);
        return $invoice->fresh();
    }

    /**
     * Devuelve un stream del PDF o XML de la factura. Útil cuando queremos
     * que Laravel proxee la descarga firmando el request con nuestra key
     * en vez de exponer la URL directa al cliente.
     */
    public function downloadPdf(string $facturapiInvoiceId): string
    {
        $res = $this->http()->withOptions(['stream' => false])
            ->get(self::BASE_URL . '/invoices/' . $facturapiInvoiceId . '/pdf');
        if (! $res->ok()) {
            throw new RuntimeException('No se pudo descargar el PDF.');
        }
        return $res->body();
    }

    public function downloadXml(string $facturapiInvoiceId): string
    {
        $res = $this->http()->get(self::BASE_URL . '/invoices/' . $facturapiInvoiceId . '/xml');
        if (! $res->ok()) {
            throw new RuntimeException('No se pudo descargar el XML.');
        }
        return $res->body();
    }

    private function http()
    {
        $key = $this->key();
        if (! $key) {
            throw new RuntimeException(
                'FACTURAPI_KEY no configurada. Configúrala en el .env del backend.',
            );
        }
        return Http::withBasicAuth($key, '')->acceptJson()->timeout(30);
    }

    private function key(): ?string
    {
        return config('services.facturapi.key');
    }
}
