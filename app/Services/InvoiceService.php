<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Exception;

/**
 * FacturaloPlus Invoice Service
 *
 * Handles electronic invoicing with FacturaloPlus API
 * Following CFDI 4.0 standards for Mexican electronic invoicing
 */
class InvoiceService
{
    /**
     * API Key provided by FacturaloPlus
     */
    protected string $apiKey;

    /**
     * Indicates if we are in test mode or production
     */
    protected bool $testMode;

    /**
     * Base URL for the current environment
     */
    protected string $baseUrl;

    /**
     * Service constructor
     */
    public function __construct()
    {
        $this->apiKey = config('facturalo.api_key');
        $this->testMode = config('facturalo.test_mode', true);

        $urls = config('facturalo.urls');
        $this->baseUrl = $this->testMode ? $urls['sandbox'] : $urls['production'];

        if (empty($this->apiKey)) {
            throw new Exception('FacturaloPlus API key is not configured');
        }
    }

    /**
     * Generate invoice from order
     */
    public function generateInvoiceFromOrder(Order $order, array $receiverData): Invoice
    {
        try {
            // Validate order
            $this->validateOrder($order);

            // Create invoice record
            $invoice = $this->createInvoiceRecord($order, $receiverData);

            // Generate CFDI JSON
            $cfdiData = $this->generateCfdiData($order, $receiverData, $invoice);

            // Send to FacturaloPlus
            $response = $this->stampInvoice($cfdiData);

            if ($response['success']) {
                // Generar PDF si no viene en la respuesta inicial
                $pdfContent = $response['pdf'];
                if (!$pdfContent && $response['uuid']) {
                    $pdfContent = $this->generatePdfFromUuid($response['uuid']);
                }

                $invoice->markAsStamped(
                    $response['uuid'],
                    $response['data'],
                    $response['xml'],
                    $pdfContent
                );

                Log::info('Invoice stamped successfully', [
                    'invoice_id' => $invoice->id,
                    'uuid' => $response['uuid'],
                    'has_pdf' => !empty($pdfContent)
                ]);
            } else {
                $invoice->markAsError($response);
                Log::error('Invoice stamping failed', ['invoice_id' => $invoice->id, 'error' => $response]);
                throw new Exception('Failed to stamp invoice: ' . $response['message']);
            }

            return $invoice;

        } catch (Exception $e) {
            Log::error('Error generating invoice', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Cancel an invoice
     */
    public function cancelInvoice(Invoice $invoice, string $reason, string $replacementUuid = null): bool
    {
        try {
            if (!$invoice->isStamped()) {
                throw new Exception('Only stamped invoices can be cancelled');
            }

            $response = Http::asForm()->post($this->baseUrl . '/cancelar2', [
                'apikey' => $this->apiKey,
                'uuid' => $invoice->uuid,
                'rfcEmisor' => $invoice->issuer_rfc,
                'rfcReceptor' => $invoice->receiver_rfc,
                'motivo' => $reason,
                'folioSustitucion' => $replacementUuid
            ]);

            $result = $response->json();

            if ($response->successful() && $result['success']) {
                $invoice->markAsCancelled($reason, $replacementUuid);
                Log::info('Invoice cancelled successfully', ['invoice_id' => $invoice->id, 'uuid' => $invoice->uuid]);
                return true;
            } else {
                Log::error('Invoice cancellation failed', ['invoice_id' => $invoice->id, 'response' => $result]);
                throw new Exception('Failed to cancel invoice: ' . ($result['message'] ?? 'Unknown error'));
            }

        } catch (Exception $e) {
            Log::error('Error cancelling invoice', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create invoice record in database
     */
    protected function createInvoiceRecord(Order $order, array $receiverData): Invoice
    {
        $issuer = config('facturalo.issuer');
        $defaults = config('facturalo.defaults');

        $serie = $defaults['serie'];
        $folio = Invoice::generateNextInvoiceNumber($serie);

        return Invoice::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'invoice_number' => $serie . '-' . $folio,
            'serie' => $serie,
            'folio' => $folio,
            'status' => Invoice::STATUS_PENDING,
            'issuer_rfc' => $issuer['rfc'],
            'issuer_name' => $issuer['name'],
            'issuer_tax_regime' => $issuer['tax_regime'],
            'issuer_postal_code' => $issuer['postal_code'],
            'receiver_rfc' => $receiverData['rfc'],
            'receiver_name' => $receiverData['name'],
            'receiver_tax_regime' => $receiverData['tax_regime'] ?? '616',
            'receiver_postal_code' => $receiverData['postal_code'],
            'receiver_cfdi_use' => $receiverData['cfdi_use'] ?? $defaults['cfdi_use'],
            'subtotal' => $this->calculateSubtotal($order),
            'tax_amount' => $this->calculateTaxAmount($order),
            'total' => $this->calculateTotal($order),
            'currency' => $defaults['currency'],
            'exchange_rate' => $defaults['exchange_rate'],
            'payment_method' => $defaults['payment_method'],
            'payment_form' => $this->mapPaymentForm($order->payment_method),
            'expedition_place' => $defaults['expedition_place'],
            'issue_date' => Carbon::now(),
            'concepts' => $this->generateConcepts($order)
        ]);
    }

    /**
     * Calculate subtotal from order items
     */
    protected function calculateSubtotal(Order $order): float
    {
        $subtotal = 0;
        foreach ($order->items as $item) {
            // unit_price includes tax, so divide by 1.16 to get price without tax
            $unitPriceWithoutTax = $item->unit_price / 1.16;
            $subtotal += $unitPriceWithoutTax * $item->quantity;
        }
        return $subtotal;
    }

    /**
     * Calculate tax amount from order items
     */
    protected function calculateTaxAmount(Order $order): float
    {
        $taxes = config('facturalo.taxes.iva');
        $subtotal = $this->calculateSubtotal($order);
        return $subtotal * $taxes['rate'];
    }

    /**
     * Calculate total amount (subtotal + taxes)
     */
    protected function calculateTotal(Order $order): float
    {
        $subtotal = $this->calculateSubtotal($order);
        $taxAmount = $this->calculateTaxAmount($order);
        return $subtotal + $taxAmount;
    }

    /**
     * Generate CFDI data in FacturaloPlus format
     */
    protected function generateCFDIDataForFacturaloPlus(Invoice $invoice): array
    {
        $order = $invoice->order;
        $issuer = config('facturalo.issuer');
        $defaults = config('facturalo.defaults');
        $taxes = config('facturalo.taxes.iva');

        // Calculate subtotal and taxes
        $subtotal = $invoice->subtotal;
        $taxAmount = $invoice->tax_amount;
        $total = $invoice->total;

        // Generate concepts for each order item
        $conceptos = [];
        foreach ($order->items as $item) {
            // Calculate unit price without tax (item->unit_price includes tax)
            $unitPriceWithoutTax = $item->unit_price / 1.16;
            $itemSubtotal = $unitPriceWithoutTax * $item->quantity;
            $itemTax = $itemSubtotal * $taxes['rate'];

            $conceptos[] = [
                'ClaveProdServ' => $defaults['product_service_code'],
                'Cantidad' => (string)$item->quantity,
                'ClaveUnidad' => $defaults['unit_code'],
                'Unidad' => $defaults['unit'],
                'Descripcion' => $item->product_name,
                'ValorUnitario' => number_format($unitPriceWithoutTax, 2, '.', ''),
                'Importe' => number_format($itemSubtotal, 2, '.', ''),
                'ObjetoImp' => '02',
                'Impuestos' => [
                    'Traslados' => [
                        [
                            'Base' => number_format($itemSubtotal, 2, '.', ''),
                            'Impuesto' => $taxes['tax_code'],
                            'TipoFactor' => $taxes['factor_type'],
                            'TasaOCuota' => number_format($taxes['rate'], 6, '.', ''),
                            'Importe' => number_format($itemTax, 2, '.', '')
                        ]
                    ]
                ]
            ];
        }

        return [
            'Comprobante' => [
                'Version' => '4.0',
                'Serie' => $invoice->serie,
                'Folio' => $invoice->folio,
                'Fecha' => $invoice->issue_date->format('Y-m-d\TH:i:s'),
                'NoCertificado' => $issuer['certificate_number'],
                'SubTotal' => number_format($subtotal, 2, '.', ''),
                'Moneda' => $invoice->currency,
                'Total' => number_format($total, 2, '.', ''),
                'TipoDeComprobante' => $defaults['voucher_type'],
                'MetodoPago' => $invoice->payment_method,
                'FormaPago' => $invoice->payment_form,
                'Exportacion' => $defaults['exportation'],
                'LugarExpedicion' => $invoice->expedition_place,
                'Emisor' => [
                    'Rfc' => $invoice->issuer_rfc,
                    'Nombre' => $invoice->issuer_name,
                    'RegimenFiscal' => $invoice->issuer_tax_regime
                ],
                'Receptor' => [
                    'Rfc' => $invoice->receiver_rfc,
                    'Nombre' => $invoice->receiver_name,
                    'UsoCFDI' => $invoice->receiver_cfdi_use,
                    'DomicilioFiscalReceptor' => $invoice->receiver_postal_code,
                    'RegimenFiscalReceptor' => $invoice->receiver_tax_regime
                ],
                'Conceptos' => $conceptos,
                'Impuestos' => [
                    'TotalImpuestosTrasladados' => number_format($taxAmount, 2, '.', ''),
                    'Traslados' => [
                        [
                            'Base' => number_format($subtotal, 2, '.', ''),
                            'Impuesto' => $taxes['tax_code'],
                            'TipoFactor' => $taxes['factor_type'],
                            'TasaOCuota' => number_format($taxes['rate'], 6, '.', ''),
                            'Importe' => number_format($taxAmount, 2, '.', '')
                        ]
                    ]
                ]
            ],
            'CamposPDF' => [
                'tipoComprobante' => 'FACTURA',
                'Comentarios' => 'Ninguno'
            ],
            'logo' => ''
        ];
    }

    /**
     * Generate CFDI data for FacturaloPlus
     */
    protected function generateCfdiData(Order $order, array $receiverData, Invoice $invoice): array
    {
        $issuer = config('facturalo.issuer');
        $defaults = config('facturalo.defaults');
        $taxes = config('facturalo.taxes.iva');

        $data = [
            'Comprobante' => [
                'Version' => '4.0',
                'Serie' => $invoice->serie,
                'Folio' => $invoice->folio,
                'Fecha' => $invoice->issue_date->format('Y-m-d\TH:i:s'),
                'NoCertificado' => $issuer['certificate_number'],
                'SubTotal' => number_format($invoice->subtotal, 2, '.', ''),
                'Moneda' => $invoice->currency,
                'Total' => number_format($invoice->total, 2, '.', ''),
                'TipoDeComprobante' => $defaults['voucher_type'],
                'MetodoPago' => $invoice->payment_method,
                'FormaPago' => $invoice->payment_form,
                'Exportacion' => $defaults['exportation'],
                'LugarExpedicion' => $invoice->expedition_place,
                'Emisor' => [
                    'Rfc' => $invoice->issuer_rfc,
                    'Nombre' => $invoice->issuer_name,
                    'RegimenFiscal' => $invoice->issuer_tax_regime
                ],
                'Receptor' => [
                    'Rfc' => $invoice->receiver_rfc,
                    'Nombre' => $invoice->receiver_name,
                    'UsoCFDI' => $invoice->receiver_cfdi_use,
                    'DomicilioFiscalReceptor' => $invoice->receiver_postal_code,
                    'RegimenFiscalReceptor' => $invoice->receiver_tax_regime
                ],
                'Conceptos' => $this->formatConceptsForCfdi($order, $taxes),
                'Impuestos' => [
                    'TotalImpuestosTrasladados' => number_format($invoice->tax_amount, 2, '.', ''),
                    'Traslados' => [
                        [
                            'Base' => number_format($invoice->subtotal, 2, '.', ''),
                            'Impuesto' => $taxes['tax_code'],
                            'TipoFactor' => $taxes['factor_type'],
                            'TasaOCuota' => number_format($taxes['rate'], 6, '.', ''),
                            'Importe' => number_format($invoice->tax_amount, 2, '.', '')
                        ]
                    ]
                ]
            ],
            'CamposPDF' => [
                'tipoComprobante' => 'FACTURA',
                'Comentarios' => 'Ninguno'
            ],
            'logo' => ''
        ];

        return $data;
    }

        /**
     * Send invoice to FacturaloPlus for stamping
     */
    protected function stampInvoice(array $cfdiData): array
    {
        try {
            // Debug output only in test mode
            if ($this->testMode) {
                echo "🔍 DEBUG - Datos enviados a FacturaloPlus:
";
                echo json_encode($cfdiData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "
";
            }

            $jsonData = json_encode($cfdiData, JSON_UNESCAPED_UNICODE);
            $jsonB64 = base64_encode($jsonData);

            if ($this->testMode) {
                echo "🔍 DEBUG - JSON Base64:
";
                echo substr($jsonB64, 0, 100) . "...

";
            }

            $response = Http::asForm()->post($this->baseUrl . '/timbrarJSON2', [
                'apikey' => $this->apiKey,
                'jsonB64' => $jsonB64,
                'keyPEM' => config('facturalo.certificates.key_pem_content'),
                'cerPEM' => config('facturalo.certificates.cert_pem_content'),
                'plantilla' => '1'  // Plantilla 1 para generar PDF
            ]);

            if ($this->testMode) {
                echo "📡 Respuesta HTTP: " . $response->status() . "
";
                echo "📋 Respuesta completa:
";
                echo $response->body() . "

";
            }

            if ($response->successful()) {
                $result = $response->json();

                if ($this->testMode) {
                    echo "📊 Estructura respuesta timbrarJSON2: " . json_encode(array_keys($result), JSON_PRETTY_PRINT) . "\n";
                }

                // FacturaloPlus indica éxito con code "200" y message que contiene "éxito"
                if (isset($result['code']) && $result['code'] == "200" &&
                    isset($result['message']) && strpos($result['message'], 'éxito') !== false) {

                    // timbrarJSON2 devuelve XML y PDF en el campo 'data'
                    $xmlContent = null;
                    $pdfContent = null;
                    $uuid = null;

                    if (isset($result['data'])) {
                        // El campo 'data' puede contener tanto XML como PDF
                        $dataContent = $result['data'];

                        // Si es un string que contiene XML, extraer UUID
                        if (is_string($dataContent) && strpos($dataContent, '<?xml') !== false) {
                            $xmlContent = $dataContent;
                            preg_match('/UUID="([^"]+)"/', $xmlContent, $matches);
                            if (isset($matches[1])) {
                                $uuid = $matches[1];
                            }
                        }
                        // Si es un array, puede contener tanto XML como PDF
                        elseif (is_array($dataContent)) {
                            $xmlContent = $dataContent['xml'] ?? $dataContent['XML'] ?? null;
                            $pdfContent = $dataContent['pdf'] ?? $dataContent['PDF'] ?? null;

                            if ($xmlContent) {
                                preg_match('/UUID="([^"]+)"/', $xmlContent, $matches);
                                if (isset($matches[1])) {
                                    $uuid = $matches[1];
                                }
                            }
                        }
                    }

                    // También verificar si PDF viene en campo separado
                    if (!$pdfContent && isset($result['pdf'])) {
                        $pdfContent = $result['pdf'];
                    }

                    if ($this->testMode) {
                        echo "✅ UUID extraído: {$uuid}\n";
                        echo "✅ XML presente: " . ($xmlContent ? 'Sí' : 'No') . "\n";
                        echo "✅ PDF presente: " . ($pdfContent ? 'Sí' : 'No') . "\n";
                    }

                    return [
                        'success' => true,
                        'uuid' => $uuid,
                        'xml' => $xmlContent,
                        'pdf' => $pdfContent,
                        'data' => $result
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => $result['message'] ?? 'Unknown error from FacturaloPlus',
                        'data' => $result
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'HTTP error: ' . $response->status(),
                    'data' => $response->json()
                ];
            }

        } catch (Exception $e) {
            if ($this->testMode) {
                echo "❌ ERROR en stampInvoice: " . $e->getMessage() . "\n";
            }

            Log::error('Error in stampInvoice', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return [
                'success' => false,
                'message' => 'Error processing stamp request: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }    /**
     * Validate order before invoicing
     */
    protected function validateOrder(Order $order): void
    {
        if ($order->payment_status !== 'paid') {
            throw new Exception('Order must be paid before invoicing');
        }

        if ($order->total_amount <= 0) {
            throw new Exception('Order total must be greater than zero');
        }

        // Check if invoice already exists
        $existingInvoice = Invoice::where('order_id', $order->id)->first();
        if ($existingInvoice) {
            throw new Exception('Invoice already exists for this order');
        }
    }

    /**
     * Generate concepts from order items
     */
    protected function generateConcepts(Order $order): array
    {
        $concepts = [];

        foreach ($order->items as $item) {
            $concepts[] = [
                'quantity' => $item->quantity,
                'unit' => 'Unidad de servicio',
                'unit_key' => 'E48',
                'description' => $item->product_title,
                'unit_price' => $item->unit_price,
                'amount' => $item->line_total
            ];
        }

        return $concepts;
    }    /**
     * Format concepts for CFDI structure
     */
    protected function formatConceptsForCfdi(Order $order, array $taxes): array
    {
        $concepts = [];

        foreach ($order->items as $item) {
            $subtotal = $item->line_total / 1.16; // Assuming 16% tax
            $taxAmount = $item->line_total - $subtotal;

            $concepts[] = [
                'ClaveProdServ' => '43232408', // Software - Computers
                'Cantidad' => (string)$item->quantity,
                'ClaveUnidad' => 'E48',
                'Unidad' => 'Unidad de servicio',
                'Descripcion' => $item->product_title,
                'ValorUnitario' => number_format($item->unit_price / 1.16, 2, '.', ''),
                'Importe' => number_format($subtotal, 2, '.', ''),
                'ObjetoImp' => '02',
                'Impuestos' => [
                    'Traslados' => [
                        [
                            'Base' => number_format($subtotal, 2, '.', ''),
                            'Impuesto' => $taxes['tax_code'],
                            'TipoFactor' => $taxes['factor_type'],
                            'TasaOCuota' => number_format($taxes['rate'], 6, '.', ''),
                            'Importe' => number_format($taxAmount, 2, '.', '')
                        ]
                    ]
                ]
            ];
        }

        return $concepts;
    }

    /**
     * Map payment method to SAT catalog
     */
    protected function mapPaymentForm(string $paymentMethod): string
    {
        $mapping = [
            'credit_card' => '04', // Tarjeta de crédito
            'debit_card' => '28',  // Tarjeta de débito
            'transfer' => '03',    // Transferencia electrónica
            'cash' => '01',        // Efectivo
            'check' => '02'        // Cheque nominativo
        ];

        return $mapping[$paymentMethod] ?? '99'; // Otros
    }

    /**
     * Test connection to FacturaloPlus
     */
    public function testConnection(): array
    {
        try {
            $response = Http::timeout(10)->asForm()->post($this->baseUrl . '/consultarCreditosDisponibles', [
                'apikey' => $this->apiKey
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Connection successful',
                    'data' => $response->json()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Connection failed: ' . $response->status(),
                    'data' => $response->json()
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Generate PDF from stamped invoice using FacturaloPlus
     */
    public function generatePdfFromUuid(string $uuid): ?string
    {
        try {
            if ($this->testMode) {
                echo "📄 Generando PDF para UUID: {$uuid}\n";
            }

            // FacturaloPlus: endpoint correcto para obtener PDF
            $response = Http::asForm()->post($this->baseUrl . '/obtenerPDF', [
                'apikey' => $this->apiKey,
                'uuid' => $uuid,
                'rfcEmisor' => config('facturalo.issuer.rfc')
            ]);

            if ($this->testMode) {
                echo "📡 Respuesta PDF HTTP: " . $response->status() . "\n";
                echo "📋 Contenido respuesta: " . substr($response->body(), 0, 200) . "...\n";
            }

            if ($response->successful()) {
                $result = $response->json();

                if ($this->testMode) {
                    echo "📊 Estructura respuesta PDF: " . json_encode(array_keys($result), JSON_PRETTY_PRINT) . "\n";
                }

                // FacturaloPlus puede devolver el PDF en diferentes formatos
                if (isset($result['success']) && $result['success'] === true && isset($result['pdf'])) {
                    if ($this->testMode) {
                        echo "✅ PDF obtenido en campo 'pdf'\n";
                    }
                    return $result['pdf'];
                } elseif (isset($result['data']) && !empty($result['data'])) {
                    if ($this->testMode) {
                        echo "✅ PDF obtenido en campo 'data'\n";
                    }
                    return $result['data'];
                } elseif (isset($result['pdf']) && !empty($result['pdf'])) {
                    if ($this->testMode) {
                        echo "✅ PDF obtenido directamente\n";
                    }
                    return $result['pdf'];
                } else {
                    if ($this->testMode) {
                        echo "❌ No se encontró PDF en la respuesta\n";
                        echo "📋 Respuesta completa: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
                    }
                    return null;
                }
            } else {
                if ($this->testMode) {
                    echo "❌ Error HTTP: " . $response->status() . "\n";
                    echo "📋 Error body: " . $response->body() . "\n";
                }
                return null;
            }

        } catch (Exception $e) {
            if ($this->testMode) {
                echo "❌ ERROR generando PDF: " . $e->getMessage() . "\n";
            }

            Log::error('Error generating PDF', [
                'uuid' => $uuid,
                'message' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Get available credits
     */
    public function getAvailableCredits(): array
    {
        try {
            $response = Http::asForm()->post($this->baseUrl . '/consultarCreditosDisponibles', [
                'apikey' => $this->apiKey
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to get credits: ' . $response->status(),
                    'data' => $response->json()
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error getting credits: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
}
