<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $this->apiKey   = config('facturalo.api_key');
        $this->testMode = config('facturalo.test_mode', true);

        $urls          = config('facturalo.urls');
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
            // Validate order BEFORE creating any records
            $this->validateOrder($order);

            // Generate CFDI JSON (without creating invoice record yet)
            $cfdiData = $this->generateCfdiDataForOrder($order, $receiverData);

            // Send to FacturaloPlus FIRST
            $response = $this->stampInvoice($cfdiData);

            if ($response['success']) {
                // Only create invoice record if FacturaloPlus succeeded
                $invoice = $this->createInvoiceRecord($order, $receiverData);

                // Generar PDF si no viene en la respuesta inicial
                $pdfContent = $response['pdf'];
                if (!$pdfContent && $response['uuid']) {
                    $pdfContent = $this->generatePdfFromUuid($response['uuid']);
                }

                $invoice->markAsStamped($response['uuid'], $response['data'], $response['xml'], $pdfContent);

                Log::info('Invoice stamped successfully', [
                    'invoice_id' => $invoice->id,
                    'uuid'       => $response['uuid'],
                    'has_pdf'    => !empty($pdfContent),
                ]);

                return $invoice;
            } else {
                // FacturaloPlus failed - DO NOT create invoice record
                $errorMessage = 'Failed to stamp invoice: ' . $response['message'];

                Log::error('Invoice stamping failed - no record created', [
                    'order_id'      => $order->id,
                    'error'         => $response,
                    'receiver_data' => $receiverData,
                ]);

                // Send error notifications
                $this->sendInvoiceErrorNotifications($order, $errorMessage, $response, $receiverData);

                throw new Exception($errorMessage);
            }
        } catch (Exception $e) {
            Log::error('Error generating invoice', [
                'order_id'      => $order->id,
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
                'receiver_data' => $receiverData,
            ]);

            // Send error notifications for any exception
            $this->sendInvoiceErrorNotifications($order, $e->getMessage(), [], $receiverData);

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
                'apikey'           => $this->apiKey,
                'uuid'             => $invoice->uuid,
                'rfcEmisor'        => $invoice->issuer_rfc,
                'rfcReceptor'      => $invoice->receiver_rfc,
                'motivo'           => $reason,
                'folioSustitucion' => $replacementUuid,
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
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create invoice record in database
     */
    protected function createInvoiceRecord(Order $order, array $receiverData): Invoice
    {
        $issuer   = config('facturalo.issuer');
        $defaults = config('facturalo.defaults');

        $serie = $defaults['serie'];
        $folio = Invoice::generateNextInvoiceNumber($serie);

        return Invoice::create([
            'order_id'             => $order->id,
            'user_id'              => $order->user_id,
            'invoice_number'       => $serie . '-' . $folio,
            'serie'                => $serie,
            'folio'                => $folio,
            'status'               => Invoice::STATUS_PENDING,
            'issuer_rfc'           => $issuer['rfc'],
            'issuer_name'          => $issuer['name'],
            'issuer_tax_regime'    => $issuer['tax_regime'],
            'issuer_postal_code'   => $issuer['postal_code'],
            'receiver_rfc'         => $receiverData['rfc'],
            'receiver_name'        => $receiverData['name'],
            'receiver_tax_regime'  => $receiverData['tax_regime'] ?? '616',
            'receiver_postal_code' => $receiverData['postal_code'],
            'receiver_cfdi_use'    => $receiverData['cfdi_use'] ?? $defaults['cfdi_use'],
            'subtotal'             => $order->subtotal,
            'tax_amount'           => $order->tax_amount,
            'total'                => $order->total_amount,
            'currency'             => $defaults['currency'],
            'exchange_rate'        => $defaults['exchange_rate'],
            'payment_method'       => $defaults['payment_method'],
            'payment_form'         => $this->mapPaymentForm($order->payment_method),
            'expedition_place'     => $defaults['expedition_place'],
            'issue_date'           => Carbon::now(),
            'concepts'             => $this->generateConcepts($order),
        ]);
    }

    /**
     * Send error notifications when invoice generation fails
     */
    private function sendInvoiceErrorNotifications(
        Order $order,
        string $errorMessage,
        array $errorDetails,
        array $receiverData
    ): void {
        try {
            $notificationService = new InvoiceErrorNotificationService();
            $notificationService->sendInvoiceErrorNotification($order, $errorMessage, $errorDetails, $receiverData);
        } catch (Exception $e) {
            // Silent fail for notification errors - don't break the main flow
            Log::error('Failed to send invoice error notifications', [
                'order_id'           => $order->id,
                'notification_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate CFDI data for order without creating invoice record first
     */
    protected function generateCfdiDataForOrder(Order $order, array $receiverData): array
    {
        $issuer   = config('facturalo.issuer');
        $defaults = config('facturalo.defaults');
        $taxes    = config('facturalo.taxes.iva');

        // Generate temporary invoice data for CFDI
        $serie     = $defaults['serie'];
        $folio     = Invoice::generateNextInvoiceNumber($serie);
        $issueDate = Carbon::now();

        // Use totals from database - they are already calculated correctly

        $exchangeRate = floatval($order->exchange_rate ?? 1);

        $totalOrderValue = round($order->total_amount * $exchangeRate, 2);
        $total           = number_format($totalOrderValue, 2, '.', '');

        $taxRate       = $taxes['rate'];
        $subtotalValue = round($totalOrderValue / ($taxRate + 1), 2);
        $subtotal      = number_format($subtotalValue, 2, '.', '');

        $taxAmount = number_format($subtotal * $taxRate, 2, '.', '');

        // Generate concepts using the database values
        $concepts = $this->formatConceptsForCfdiFromOrder($order, $taxes);

        return [
            'Comprobante' => [
                'Version'           => '4.0',
                'Serie'             => $serie,
                'Folio'             => $folio,
                'Fecha'             => $issueDate->format('Y-m-d\TH:i:s'),
                'NoCertificado'     => $issuer['certificate_number'],
                'SubTotal'          => $subtotal,
                'Moneda'            => $defaults['currency'],
                'Total'             => $total,
                'TipoDeComprobante' => $defaults['voucher_type'],
                'MetodoPago'        => $defaults['payment_method'],
                'FormaPago'         => $this->mapPaymentForm($order->payment_method ?? 'credit_card'),
                'Exportacion'       => $defaults['exportation'],
                'LugarExpedicion'   => $defaults['expedition_place'],
                'Emisor'            => [
                    'Rfc'           => $issuer['rfc'],
                    'Nombre'        => $issuer['name'],
                    'RegimenFiscal' => $issuer['tax_regime'],
                ],
                'Receptor'          => [
                    'Rfc'                     => $receiverData['rfc'],
                    'Nombre'                  => $receiverData['name'],
                    'UsoCFDI'                 => $receiverData['cfdi_use'] ?? $defaults['cfdi_use'],
                    'DomicilioFiscalReceptor' => $receiverData['postal_code'],
                    'RegimenFiscalReceptor'   => $receiverData['tax_regime'] ?? '616',
                ],
                'Conceptos'         => $concepts,
                'Impuestos'         => [
                    'TotalImpuestosTrasladados' => $taxAmount,
                    'Traslados'                 => [
                        [
                            'Base'       => $subtotal,
                            'Impuesto'   => $taxes['tax_code'],
                            'TipoFactor' => $taxes['factor_type'],
                            'TasaOCuota' => number_format($taxes['rate'], 6, '.', ''),
                            'Importe'    => $taxAmount,
                        ],
                    ],
                ],
            ],
            'CamposPDF'   => [
                'tipoComprobante' => 'FACTURA',
                'Comentarios'     => 'Ninguno',
            ],
            'logo'        => '',
        ];
    }

    /**
     * Format concepts for CFDI structure from order (without invoice record)
     */
    protected function formatConceptsForCfdiFromOrder(Order $order, array $taxes): array
    {
        $concepts = [];
        $defaults = config('facturalo.defaults');

        // Get totals from database - these are the authoritative values
        $exchangeRate    = floatval($order->exchange_rate ?? 1);
        $totalOrderValue = round($order->total_amount * $exchangeRate, 2);

        $taxRate        = $taxes['rate'];
        $orderSubtotal  = round($totalOrderValue / ($taxRate + 1), 2);
        $orderTaxAmount = round($orderSubtotal * $taxRate, 2);

        // Calculate total from items to use for proportional distribution
        $itemsTotal = $order->items->sum('line_total') * $exchangeRate;

        foreach ($order->items as $item) {
            // Calculate proportional subtotal and tax based on order totals
            $itemRatio        = floatval($item->line_total) / $itemsTotal;
            $itemSubtotal     = $orderSubtotal * $itemRatio * $exchangeRate;
            $itemTaxAmount    = $orderTaxAmount * $itemRatio * $exchangeRate;
            $itemUnitSubtotal = $itemSubtotal / $item->quantity;

            $concepts[] = [
                'ClaveProdServ' => $defaults['product_service_code'] ?? '43232408', // Software - Computers
                'Cantidad'      => (string) $item->quantity,
                'ClaveUnidad'   => $defaults['unit_code'] ?? 'E48',
                'Unidad'        => $defaults['unit'] ?? 'Unidad de servicio',
                'Descripcion'   => $item->product_title ?? $item->product->ProductTitle ?? 'Producto sin nombre',
                'ValorUnitario' => number_format($itemUnitSubtotal, 2, '.', ''),
                'Importe'       => number_format($itemSubtotal, 2, '.', ''),
                'ObjetoImp'     => '02',
                'Impuestos'     => [
                    'Traslados' => [
                        [
                            'Base'       => number_format($itemSubtotal, 2, '.', ''),
                            'Impuesto'   => $taxes['tax_code'],
                            'TipoFactor' => $taxes['factor_type'],
                            'TasaOCuota' => number_format($taxes['rate'], 6, '.', ''),
                            'Importe'    => number_format($itemTaxAmount, 2, '.', ''),
                        ],
                    ],
                ],
            ];
        }

        return $concepts;
    }

    /**
     * Generate CFDI data for FacturaloPlus
     */
    protected function generateCfdiData(Order $order, array $receiverData, Invoice $invoice): array
    {
        $issuer   = config('facturalo.issuer');
        $defaults = config('facturalo.defaults');
        $taxes    = config('facturalo.taxes.iva');

        $data = [
            'Comprobante' => [
                'Version'           => '4.0',
                'Serie'             => $invoice->serie,
                'Folio'             => $invoice->folio,
                'Fecha'             => $invoice->issue_date->format('Y-m-d\TH:i:s'),
                'NoCertificado'     => $issuer['certificate_number'],
                'SubTotal'          => number_format($invoice->subtotal, 2, '.', ''),
                'Moneda'            => $invoice->currency,
                'Total'             => number_format($invoice->total, 2, '.', ''),
                'TipoDeComprobante' => $defaults['voucher_type'],
                'MetodoPago'        => $invoice->payment_method,
                'FormaPago'         => $invoice->payment_form,
                'Exportacion'       => $defaults['exportation'],
                'LugarExpedicion'   => $invoice->expedition_place,
                'Emisor'            => [
                    'Rfc'           => $invoice->issuer_rfc,
                    'Nombre'        => $invoice->issuer_name,
                    'RegimenFiscal' => $invoice->issuer_tax_regime,
                ],
                'Receptor'          => [
                    'Rfc'                     => $invoice->receiver_rfc,
                    'Nombre'                  => $invoice->receiver_name,
                    'UsoCFDI'                 => $invoice->receiver_cfdi_use,
                    'DomicilioFiscalReceptor' => $invoice->receiver_postal_code,
                    'RegimenFiscalReceptor'   => $invoice->receiver_tax_regime,
                ],
                'Conceptos'         => $this->formatConceptsForCfdi($order, $taxes),
                'Impuestos'         => [
                    'TotalImpuestosTrasladados' => number_format($invoice->tax_amount, 2, '.', ''),
                    'Traslados'                 => [
                        [
                            'Base'       => number_format($invoice->subtotal, 2, '.', ''),
                            'Impuesto'   => $taxes['tax_code'],
                            'TipoFactor' => $taxes['factor_type'],
                            'TasaOCuota' => number_format($taxes['rate'], 6, '.', ''),
                            'Importe'    => number_format($invoice->tax_amount, 2, '.', ''),
                        ],
                    ],
                ],
            ],
            'CamposPDF'   => [
                'tipoComprobante' => 'FACTURA',
                'Comentarios'     => 'Ninguno',
            ],
            'logo'        => '',
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
                Log::info('FacturaloPlus CFDI Data', [
                    'cfdi_data' => $cfdiData,
                ]);
            }

            $jsonData = json_encode($cfdiData, JSON_UNESCAPED_UNICODE);
            $jsonB64  = base64_encode($jsonData);

            if ($this->testMode) {
                Log::info('FacturaloPlus JSON Base64', [
                    'json_b64_preview' => substr($jsonB64, 0, 100) . '...',
                ]);
            }

            $response = Http::asForm()->post($this->baseUrl . '/timbrarJSON2', [
                'apikey'    => $this->apiKey,
                'jsonB64'   => $jsonB64,
                'keyPEM'    => config('facturalo.certificates.key_pem_content'),
                'cerPEM'    => config('facturalo.certificates.cert_pem_content'),
                'plantilla' => '1',  // Plantilla 1 para generar PDF
            ]);

            if ($this->testMode) {
                Log::info('FacturaloPlus HTTP Response', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }

            if ($response->successful()) {
                $result = $response->json();

                if ($this->testMode) {
                    Log::info('FacturaloPlus Response Structure', [
                        'response_keys' => array_keys($result),
                    ]);
                }

                // FacturaloPlus indica éxito con code "200" y message que contiene "éxito"
                if (isset($result['code']) && $result['code'] == "200" && isset($result['message']) && strpos($result['message'],
                        'éxito') !== false) {

                    // timbrarJSON2 devuelve XML y PDF en el campo 'data'
                    $xmlContent = null;
                    $pdfContent = null;
                    $uuid       = null;

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
                        } // Si es un array, puede contener tanto XML como PDF
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
                        Log::info('FacturaloPlus Success Response', [
                            'uuid'        => $uuid,
                            'xml_present' => $xmlContent ? 'Yes' : 'No',
                            'pdf_present' => $pdfContent ? 'Yes' : 'No',
                        ]);
                    }

                    return [
                        'success' => true,
                        'uuid'    => $uuid,
                        'xml'     => $xmlContent,
                        'pdf'     => $pdfContent,
                        'data'    => $result,
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => $result['message'] ?? 'Unknown error from FacturaloPlus',
                        'data'    => $result,
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'HTTP error: ' . $response->status(),
                    'data'    => $response->json(),
                ];
            }
        } catch (Exception $e) {
            if ($this->testMode) {
                Log::error('Error in stampInvoice (test mode)', [
                    'error' => $e->getMessage(),
                ]);
            }

            Log::error('Error in stampInvoice', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return [
                'success' => false,
                'message' => 'Error processing stamp request: ' . $e->getMessage(),
                'data'    => null,
            ];
        }
    }

    /**
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
                'quantity'    => $item->quantity,
                'unit'        => 'Unidad de servicio',
                'unit_key'    => 'E48',
                'description' => $item->product_title,
                'unit_price'  => $item->unit_price,
                'amount'      => $item->line_total,
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
            'debit_card'  => '28',  // Tarjeta de débito
            'transfer'    => '03',    // Transferencia electrónica
            'cash'        => '01',        // Efectivo
            'check'       => '02',        // Cheque nominativo
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
                'apikey' => $this->apiKey,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Connection successful',
                    'data'    => $response->json(),
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Connection failed: ' . $response->status(),
                    'data'    => $response->json(),
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
                'data'    => null,
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
                Log::info('Generating PDF for UUID', ['uuid' => $uuid]);
            }

            // FacturaloPlus: endpoint correcto para obtener PDF
            $response = Http::asForm()->post($this->baseUrl . '/obtenerPDF', [
                'apikey'    => $this->apiKey,
                'uuid'      => $uuid,
                'rfcEmisor' => config('facturalo.issuer.rfc'),
            ]);

            if ($this->testMode) {
                Log::info('PDF Response', [
                    'status'       => $response->status(),
                    'body_preview' => substr($response->body(), 0, 200) . '...',
                ]);
            }

            if ($response->successful()) {
                $result = $response->json();

                if ($this->testMode) {
                    Log::info('PDF Response Structure', [
                        'response_keys' => array_keys($result),
                    ]);
                }

                // FacturaloPlus puede devolver el PDF en diferentes formatos
                if (isset($result['success']) && $result['success'] === true && isset($result['pdf'])) {
                    if ($this->testMode) {
                        Log::info('PDF obtained from pdf field');
                    }

                    return $result['pdf'];
                } elseif (isset($result['data']) && !empty($result['data'])) {
                    if ($this->testMode) {
                        Log::info('PDF obtained from data field');
                    }

                    return $result['data'];
                } elseif (isset($result['pdf']) && !empty($result['pdf'])) {
                    if ($this->testMode) {
                        Log::info('PDF obtained directly');
                    }

                    return $result['pdf'];
                } else {
                    if ($this->testMode) {
                        Log::warning('PDF not found in response', [
                            'response' => $result,
                        ]);
                    }

                    return null;
                }
            } else {
                if ($this->testMode) {
                    Log::error('PDF HTTP Error', [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                }

                return null;
            }
        } catch (Exception $e) {
            if ($this->testMode) {
                Log::error('PDF Generation Error (test mode)', [
                    'error' => $e->getMessage(),
                    'uuid'  => $uuid,
                ]);
            }

            Log::error('Error generating PDF', [
                'uuid'    => $uuid,
                'message' => $e->getMessage(),
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
                'apikey' => $this->apiKey,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data'    => $response->json(),
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to get credits: ' . $response->status(),
                    'data'    => $response->json(),
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error getting credits: ' . $e->getMessage(),
                'data'    => null,
            ];
        }
    }
}
