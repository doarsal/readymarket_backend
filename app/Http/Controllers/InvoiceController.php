<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * Controller for handling invoice operations
 * Manages CFDI 4.0 electronic invoicing through FacturaloPlus
 */
class InvoiceController extends Controller
{
    protected InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/invoices/generate-from-order/{orderId}",
     *     summary="Generate invoice from order ID",
     *     description="Generates a CFDI 4.0 electronic invoice for a paid order using default test data",
     *     tags={"Invoices"},
     *     @OA\Parameter(
     *         name="orderId",
     *         in="path",
     *         required=true,
     *         description="Order ID to generate invoice for",
     *         @OA\Schema(type="integer", example=25)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoice generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice generated successfully from order #25"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="invoice_info",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=33),
     *                     @OA\Property(property="number", type="string", example="FAC-000001"),
     *                     @OA\Property(property="uuid", type="string", example="12345678-1234-1234-1234-123456789012"),
     *                     @OA\Property(property="status", type="string", example="stamped"),
     *                     @OA\Property(property="total", type="string", example="546.00")
     *                 ),
     *                 @OA\Property(
     *                     property="download_urls",
     *                     type="object",
     *                     @OA\Property(property="pdf", type="string", example="http://localhost:8000/api/v1/invoices/33/download/pdf"),
     *                     @OA\Property(property="xml", type="string", example="http://localhost:8000/api/v1/invoices/33/download/xml")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Order not paid or invalid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Order #25 must be paid before invoicing")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Order #25 not found")
     *         )
     *     )
     * )
     *
     * Generate invoice from order ID only (with default test data)
     * Perfect for testing - uses default Mexican test RFC
     *
     * @param int $orderId
     * @return JsonResponse
     */
    public function generateFromOrderId(int $orderId): JsonResponse
    {
        // Log inicial para debug
        Log::info('=== INVOICE GENERATION REQUEST ===', [
            'order_id' => $orderId,
            'timestamp' => now(),
            'request_ip' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);

        try {
            Log::info('Starting invoice generation for order', ['order_id' => $orderId]);

            $order = Order::with(['user', 'items'])->findOrFail($orderId);
            Log::info('Order loaded successfully', [
                'order_id' => $orderId,
                'payment_status' => $order->payment_status,
                'total_amount' => $order->total_amount
            ]);

            // Verify order is paid
            if ($order->payment_status !== 'paid') {
                Log::warning('Order not paid', ['order_id' => $orderId, 'status' => $order->payment_status]);
                return response()->json([
                    'success' => false,
                    'message' => "Order #{$orderId} must be paid before invoicing. Current status: {$order->payment_status}"
                ], 400);
            }

            // Check if already has invoice
            $existingInvoice = Invoice::where('order_id', $orderId)->first();
            if ($existingInvoice) {
                Log::info('Invoice already exists', ['order_id' => $orderId, 'invoice_id' => $existingInvoice->id]);
                return response()->json([
                    'success' => true,
                    'message' => 'Invoice already exists for this order',
                    'data' => [
                        'invoice' => $existingInvoice,
                        'invoice_info' => [
                            'id' => $existingInvoice->id,
                            'number' => $existingInvoice->invoice_number,
                            'uuid' => $existingInvoice->uuid,
                            'status' => $existingInvoice->status,
                            'total' => $existingInvoice->total
                        ],
                        'download_urls' => [
                            'pdf' => route('invoices.download.pdf', $existingInvoice->id),
                            'xml' => route('invoices.download.xml', $existingInvoice->id)
                        ]
                    ]
                ]);
            }

            // Load billing information from order with tax regime relation
            $order->load(['billingInformation.taxRegime', 'billingInformation.cfdiUsage']);

            if (!$order->billingInformation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order #' . $orderId . ' does not have billing information'
                ], 400);
            }

            $billing = $order->billingInformation;

            // Use test data in test mode, real data in production
            if (config('facturalo.test_mode', true)) {
                // Test mode: use hardcoded test data
                $receiverData = [
                    'rfc' => 'XAXX010101000', // RFC genérico para pruebas del SAT
                    'name' => 'Cliente de Prueba S.A. de C.V.',
                    'postal_code' => '26015', // Debe coincidir con LugarExpedicion
                    'tax_regime' => '616', // Sin obligaciones fiscales
                    'cfdi_use' => 'S01' // Sin efectos fiscales
                ];
            } else {
                // Production mode: use real billing data from order
                $receiverData = [
                    'rfc' => $billing->rfc,
                    'name' => $billing->organization,
                    'postal_code' => $billing->postal_code,
                    'tax_regime' => $billing->taxRegime ? $billing->taxRegime->code : '616',
                    'cfdi_use' => $billing->cfdiUsage ? $billing->cfdiUsage->code : 'S01'
                ];
            }

            Log::info('Starting invoice service generation', [
                'order_id' => $orderId,
                'receiver_data' => $receiverData
            ]);

            $invoice = $this->invoiceService->generateInvoiceFromOrder($order, $receiverData);

            Log::info('Invoice generated successfully', [
                'order_id' => $orderId,
                'invoice_id' => $invoice->id,
                'uuid' => $invoice->uuid,
                'status' => $invoice->status
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invoice generated successfully from order #' . $orderId,
                'data' => [
                    'invoice' => $invoice->load(['order', 'user']),
                    'order_info' => [
                        'id' => $order->id,
                        'number' => $order->order_number,
                        'total' => $order->total_amount,
                        'status' => $order->payment_status
                    ],
                    'invoice_info' => [
                        'id' => $invoice->id,
                        'number' => $invoice->invoice_number,
                        'uuid' => $invoice->uuid,
                        'status' => $invoice->status,
                        'subtotal' => $invoice->subtotal,
                        'tax' => $invoice->tax_amount,
                        'total' => $invoice->total
                    ],
                    'download_urls' => [
                        'pdf' => route('invoices.download.pdf', $invoice->id),
                        'xml' => route('invoices.download.xml', $invoice->id)
                    ]
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Order not found', ['order_id' => $orderId]);
            return response()->json([
                'success' => false,
                'message' => "Order #{$orderId} not found"
            ], 404);

        } catch (Exception $e) {
            Log::error('Error generating invoice from order ID', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            // Ensure we always return a JSON response
            return response()->json([
                'success' => false,
                'message' => 'Error al generar la factura: ' . $e->getMessage(),
                'error_details' => [
                    'type' => get_class($e),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine(),
                    'order_id' => $orderId
                ]
            ], 500);
        } catch (\Throwable $e) {
            Log::critical('Critical error generating invoice from order ID', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error crítico al procesar la solicitud',
                'error_details' => [
                    'type' => get_class($e),
                    'message' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Generate invoice from order
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateInvoice(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'order_id' => 'required|integer|exists:orders,id',
                'receiver_rfc' => 'required|string|min:12|max:13',
                'receiver_name' => 'required|string|max:255',
                'receiver_postal_code' => 'required|string|size:5',
                'receiver_tax_regime' => 'nullable|string|size:3',
                'receiver_cfdi_use' => 'nullable|string|size:3'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $order = Order::with(['user', 'items'])->findOrFail($request->order_id);

            // Check if user owns the order
            if (auth()->check() && $order->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to order'
                ], 403);
            }

            $receiverData = [
                'rfc' => strtoupper($request->receiver_rfc),
                'name' => $request->receiver_name,
                'postal_code' => $request->receiver_postal_code,
                'tax_regime' => $request->receiver_tax_regime ?? '616',
                'cfdi_use' => $request->receiver_cfdi_use ?? 'G03'
            ];

            $invoice = $this->invoiceService->generateInvoiceFromOrder($order, $receiverData);

            return response()->json([
                'success' => true,
                'message' => 'Invoice generated successfully',
                'data' => [
                    'invoice' => $invoice->load(['order', 'user']),
                    'download_urls' => [
                        'pdf' => route('invoices.download.pdf', $invoice->id),
                        'xml' => route('invoices.download.xml', $invoice->id)
                    ]
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error generating invoice', [
                'order_id' => $request->order_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error generating invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invoice details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $invoice = Invoice::with(['order', 'user'])->findOrFail($id);

            // Check if user owns the invoice
            if (auth()->check() && $invoice->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to invoice'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $invoice
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }
    }

    /**
     * List user invoices
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Invoice::with(['order', 'user']);

            // Filter by authenticated user if logged in
            if (auth()->check()) {
                $query->where('user_id', auth()->id());
            }

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('from_date')) {
                $query->whereDate('issue_date', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->whereDate('issue_date', '<=', $request->to_date);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                      ->orWhere('receiver_name', 'like', "%{$search}%")
                      ->orWhere('receiver_rfc', 'like', "%{$search}%");
                });
            }

            $invoices = $query->orderBy('created_at', 'desc')
                             ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $invoices
            ]);

        } catch (Exception $e) {
            Log::error('Error listing invoices', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving invoices'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/invoices/{id}/download/pdf",
     *     summary="Download invoice PDF",
     *     description="Downloads the PDF file of a stamped invoice",
     *     tags={"Invoices"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="integer", example=33)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PDF file download",
     *         @OA\MediaType(
     *             mediaType="application/pdf",
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invoice not stamped",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invoice is not stamped yet")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invoice not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invoice not found")
     *         )
     *     )
     * )
     *
     * Download invoice PDF
     *
     * @param int $id
     * @return mixed
     */
    public function downloadPdf(int $id)
    {
        try {
            $invoice = Invoice::findOrFail($id);

            if (!$invoice->isStamped()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice is not stamped yet'
                ], 400);
            }

            $filename = "factura_{$invoice->invoice_number}.pdf";

            // Intentar usar archivo físico primero
            if ($invoice->hasPhysicalPdfFile()) {
                $filePath = storage_path('app/' . $invoice->getPdfFilePath());

                if (file_exists($filePath)) {
                    return response()->download($filePath, $filename, [
                        'Content-Type' => 'application/pdf'
                    ]);
                }
            }

            // Fallback a contenido Base64 de la BD
            if (!$invoice->pdf_content && $invoice->uuid) {
                $pdfContent = $this->invoiceService->generatePdfFromUuid($invoice->uuid);
                if ($pdfContent) {
                    $invoice->pdf_content = $pdfContent;
                    $invoice->save();

                    // Intentar guardar el archivo físico para futuros usos
                    $invoice->savePhysicalFiles($invoice->xml_content, $pdfContent);
                }
            }

            if (!$invoice->pdf_content) {
                return response()->json([
                    'success' => false,
                    'message' => 'PDF not available for this invoice'
                ], 404);
            }

            $pdfContent = base64_decode($invoice->pdf_content);

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
                ->header('Content-Length', strlen($pdfContent));

        } catch (Exception $e) {
            Log::error('Error downloading PDF', [
                'invoice_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error downloading PDF'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/invoices/{id}/download/xml",
     *     summary="Download invoice XML",
     *     description="Downloads the XML file of a stamped invoice (CFDI)",
     *     tags={"Invoices"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="integer", example=33)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="XML file download",
     *         @OA\MediaType(
     *             mediaType="application/xml",
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invoice not stamped",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invoice is not stamped yet")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invoice not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invoice not found")
     *         )
     *     )
     * )
     *
     * Download invoice XML
     *
     * @param int $id
     * @return mixed
     */
    public function downloadXml(int $id)
    {
        try {
            $invoice = Invoice::findOrFail($id);

            if (!$invoice->isStamped()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice is not stamped yet'
                ], 400);
            }

            $filename = "factura_{$invoice->invoice_number}.xml";

            // Intentar usar archivo físico primero
            if ($invoice->hasPhysicalXmlFile()) {
                $filePath = storage_path('app/' . $invoice->getXmlFilePath());

                if (file_exists($filePath)) {
                    return response()->download($filePath, $filename, [
                        'Content-Type' => 'application/xml'
                    ]);
                }
            }

            // Fallback a contenido de la BD
            if (!$invoice->xml_content) {
                return response()->json([
                    'success' => false,
                    'message' => 'XML not available for this invoice'
                ], 404);
            }

            $xmlContent = $invoice->xml_content;

            return response($xmlContent)
                ->header('Content-Type', 'application/xml')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
                ->header('Content-Length', strlen($xmlContent));

        } catch (Exception $e) {
            Log::error('Error downloading XML', [
                'invoice_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error downloading XML'
            ], 500);
        }
    }

    /**
     * Cancel an invoice
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function cancel(int $id, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:255',
                'replacement_uuid' => 'nullable|string|size:36'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $invoice = Invoice::findOrFail($id);

            // Check if user owns the invoice
            if (auth()->check() && $invoice->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to invoice'
                ], 403);
            }

            $success = $this->invoiceService->cancelInvoice(
                $invoice,
                $request->reason,
                $request->replacement_uuid
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Invoice cancelled successfully',
                    'data' => $invoice->fresh()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel invoice'
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Error cancelling invoice', [
                'invoice_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error cancelling invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invoice status from SAT
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getStatus(int $id): JsonResponse
    {
        try {
            $invoice = Invoice::findOrFail($id);

            // Check if user owns the invoice
            if (auth()->check() && $invoice->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to invoice'
                ], 403);
            }

            if (!$invoice->isStamped()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice is not stamped yet'
                ], 400);
            }

            // Here you would implement SAT status checking
            // For now, return the current status
            return response()->json([
                'success' => true,
                'data' => [
                    'invoice_id' => $invoice->id,
                    'uuid' => $invoice->uuid,
                    'status' => $invoice->status,
                    'sat_status' => 'vigente', // This would come from SAT API
                    'last_checked' => now()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error getting invoice status', [
                'invoice_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error getting invoice status'
            ], 500);
        }
    }

    /**
     * Test FacturaloPlus connection and get credits
     *
     * @return JsonResponse
     */
    public function testConnection(): JsonResponse
    {
        try {
            $connectionTest = $this->invoiceService->testConnection();
            $creditsInfo = $this->invoiceService->getAvailableCredits();

            return response()->json([
                'success' => true,
                'data' => [
                    'connection' => $connectionTest,
                    'credits' => $creditsInfo
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
