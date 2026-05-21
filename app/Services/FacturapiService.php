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

    // SAT ClaveProdServ por combustible. DEBE ser igual a ClaveHYP del
    // complemento de hidrocarburos.
    //   15101505 → gasolinas (Magna y Premium)
    //   15101514 → diésel
    //   15101515 → turbosina
    private const PRODUCT_KEYS = [
        'magna' => '15101505',
        'premium' => '15101505',
        'diesel' => '15101514',
    ];

    // SubProductoHYP del complemento por combustible (catálogo SAT)
    private const SUB_PRODUCTO_HYP = [
        'magna' => 'SP18',
        'premium' => 'SP19',
        'diesel' => 'SP16',
    ];

    private const HYP_NAMESPACE_URI = 'http://www.sat.gob.mx/hidrocarburospetroliferos';
    private const HYP_SCHEMA_LOCATION = 'http://www.sat.gob.mx/sitio_internet/cfd/hidrocarburospetroliferos.xsd';

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

        $invoice->update(['estado' => 'processing']);

        $fuel = $ticket->tipo_combustible;
        $productKey = self::PRODUCT_KEYS[$fuel] ?? '15101505';

        // Datos del permiso CRE del emisor — el SAT lo exige en el complemento
        // y como NoIdentificacion del concepto. Sin esto, el CFDI se rechaza.
        $numeroPermiso = (string) config('services.facturapi.cre_numero_permiso');
        $tipoPermiso   = (string) config('services.facturapi.cre_tipo_permiso', 'PER03');
        if (! $numeroPermiso) {
            $msg = 'Falta configurar el número de permiso CRE (FACTURAPI_CRE_NUMERO_PERMISO en .env).';
            $invoice->update(['estado' => 'error', 'error_message' => $msg]);
            throw new RuntimeException($msg);
        }

        $litros = max((float) $ticket->litros, 0.01);
        $cuotaIeps = $this->iepsCuotaForFuel($fuel);

        // Cálculo correcto IEPS Cuota + IVA, según docs Facturapi y la guía:
        //   precio_pvp        = monto_total / litros (con IVA + IEPS adentro)
        //   precio_sin_ieps   = precio_pvp - cuota_ieps
        //   precio_base       = precio_sin_ieps / (1 + IVA)
        //   valor_unitario    = precio_base + cuota_ieps   (incluye IEPS, sin IVA)
        $precioPvp     = (float) $ticket->monto / $litros;
        $precioSinIeps = $precioPvp - $cuotaIeps;
        $precioBase    = $precioSinIeps / (1 + self::TAX_RATE_IVA);
        $valorUnitario = round($precioBase + $cuotaIeps, 6);

        $payload = [
            'customer' => $profile->facturapi_customer_id,
            'items' => [[
                'quantity' => $litros,
                'product' => [
                    'description' => "Carga de " . ucfirst($fuel) . " — folio {$ticket->folio}",
                    'product_key' => $productKey,
                    'unit_key' => self::UNIT_KEY_LITER,
                    'price' => $valorUnitario,
                    'tax_included' => false, // IVA se calcula sobre (valor - IEPS)
                    'sku' => $numeroPermiso, // Va al NoIdentificacion del concepto
                    'taxes' => [
                        ['type' => 'IVA', 'rate' => self::TAX_RATE_IVA, 'factor' => 'Tasa'],
                        [
                            'type' => 'IEPS',
                            'rate' => $cuotaIeps,
                            'factor' => 'Cuota',
                            'withholding' => false,
                            'ieps_mode' => 'subtract_before_break_down',
                        ],
                    ],
                ],
                // Complemento HidroYPetro — string XML inline. El namespace va
                // declarado dentro del elemento Y a nivel documento (abajo).
                'complement' => $this->buildHidrocarburosComplementXml(
                    $tipoPermiso, $numeroPermiso, $productKey, self::SUB_PRODUCTO_HYP[$fuel] ?? 'SP18',
                ),
            ]],
            'use' => $invoice->uso_cfdi,
            'payment_form' => $invoice->payment_form,
            'payment_method' => 'PUE', // pago en una exhibición
            'namespaces' => [[
                'prefix' => 'hidrocarburospetroliferos',
                'uri' => self::HYP_NAMESPACE_URI,
                'schema_location' => self::HYP_SCHEMA_LOCATION,
            ]],
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

    /**
     * Construye el XML inline del complemento HidroYPetro. El namespace va
     * declarado en el mismo elemento (Facturapi requiere esto incluso si
     * también está en `namespaces[]`).
     */
    private function buildHidrocarburosComplementXml(
        string $tipoPermiso,
        string $numeroPermiso,
        string $claveHyp,
        string $subProductoHyp,
    ): string {
        return sprintf(
            '<hidrocarburospetroliferos:HidroYPetro xmlns:hidrocarburospetroliferos="%s" Version="1.0" TipoPermiso="%s" NumeroPermiso="%s" ClaveHYP="%s" SubProductoHYP="%s"/>',
            self::HYP_NAMESPACE_URI,
            $tipoPermiso,
            htmlspecialchars($numeroPermiso, ENT_QUOTES | ENT_XML1),
            $claveHyp,
            $subProductoHyp,
        );
    }

    private function iepsCuotaForFuel(string $fuel): float
    {
        return match ($fuel) {
            'magna' => (float) config('services.facturapi.ieps_cuota_magna', 0),
            'premium' => (float) config('services.facturapi.ieps_cuota_premium', 0),
            'diesel' => (float) config('services.facturapi.ieps_cuota_diesel', 0),
            default => 0.0,
        };
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
