<?php

namespace App\Http\Controllers;

use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;

/**
 * Electronic Invoicing Controller
 *
 * Controller for generating Mexican electronic invoices (CFDI 4.0) using FacturaloPlus
 */
class InvoiceController extends Controller
{
    /**
     * The invoicing service
     */
    protected $invoiceService;

    /**
     * Controller constructor
     */
    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
        $this->middleware('auth:sanctum')->except(['testConnection']);
    }

    /**
     * Test connection with the invoicing service
     *
     * @OA\Get(
     *     path="/api/invoicing/test-connection",
     *     tags={"Invoicing"},
     *     summary="Test connection with FacturaloPlus API",
     *     description="Tests the connection with the FacturaloPlus API and returns available credits",
     *     @OA\Response(
     *         response=200,
     *         description="Connection successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="credits", type="integer", example=1000),
     *                 @OA\Property(property="message", type="string", example="Connection successful")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Connection failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Connection failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function testConnection()
    {
        $result = $this->invoiceService->getAvailableCredits();

        return response()->json($result);
    }

    /**
     * Generate invoice from an order
     *
     * @OA\Post(
     *     path="/api/invoicing/generate",
     *     tags={"Invoicing"},
     *     summary="Generate CFDI 4.0 invoice",
     *     description="Generates a Mexican electronic invoice (CFDI 4.0) from an order",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"order_id", "cfdi_usage", "tax_id", "business_name", "zip_code", "tax_regime", "payment_form", "payment_method"},
     *             @OA\Property(property="order_id", type="integer", example=123),
     *             @OA\Property(property="cfdi_usage", type="string", example="G03"),
     *             @OA\Property(property="tax_id", type="string", example="XAXX010101000"),
     *             @OA\Property(property="business_name", type="string", example="John Doe"),
     *             @OA\Property(property="zip_code", type="string", example="64000"),
     *             @OA\Property(property="tax_regime", type="string", example="616"),
     *             @OA\Property(property="payment_form", type="string", example="99"),
     *             @OA\Property(property="payment_method", type="string", example="PUE")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoice generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice generated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid invoice data"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Order not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error generating invoice",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error generating invoice"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function generateInvoice(Request $request)
    {
        // Validate input data
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer',
            'cfdi_usage' => 'required|string|size:3',
            'tax_id' => 'required|string|min:12|max:13',
            'business_name' => 'required|string|max:255',
            'zip_code' => 'required|string|size:5',
            'tax_regime' => 'required|string|size:3',
            'payment_form' => 'required|string|size:2',
            'payment_method' => 'required|string|size:3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid invoice data',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Get order details (adapt this to your data model)
        // You'll need to implement the logic to retrieve order details
        $order = \App\Models\Order::with('items.product')->find($request->order_id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        // Prepare invoice data
        $invoiceData = [
            'series' => 'A',
            'folio' => $order->id,
            'payment_form' => $request->payment_form,
            'subtotal' => $order->subtotal,
            'currency' => 'MXN',
            'total' => $order->total,
            'payment_method' => $request->payment_method,
            'expedition_place' => config('services.facturalo.cp'),
            'issuer' => [
                'tax_id' => config('services.facturalo.rfc'),
                'name' => config('services.facturalo.razon_social'),
                'tax_regime' => config('services.facturalo.regimen_fiscal'),
            ],
            'receiver' => [
                'tax_id' => $request->tax_id,
                'name' => $request->business_name,
                'zip_code' => $request->zip_code,
                'tax_regime' => $request->tax_regime,
                'cfdi_usage' => $request->cfdi_usage,
            ],
            'items' => [],
            'total_transferred_taxes' => 0,
            'tax_base' => 0,
        ];

        // Process order items (products)
        foreach ($order->items as $item) {
            $amount = $item->price * $item->quantity;
            $taxAmount = $amount * 0.16;

            $invoiceData['items'][] = [
                'product_key' => $item->product->sat_key ?? '43232408', // SAT code for software
                'quantity' => $item->quantity,
                'unit_key' => 'E48',  // Service unit for software
                'unit' => 'Service',
                'description' => $item->product->name,
                'unit_price' => $item->price,
                'amount' => $amount,
                'tax_amount' => $taxAmount,
            ];

            $invoiceData['total_transferred_taxes'] += $taxAmount;
            $invoiceData['tax_base'] += $amount;
        }

        // Decide whether to use XML or JSON based on preference
        $useJson = true; // Change based on preference

        if ($useJson) {
            $jsonData = $this->invoiceService->prepareJsonCfdi40Data($invoiceData);
            $result = $this->invoiceService->stampJson($jsonData);
        } else {
            $xml = $this->invoiceService->generateXmlCfdi40($invoiceData);
            $result = $this->invoiceService->stampXml($xml);
        }

        // If invoicing was successful, update the order with invoice data
        if ($result['success']) {
            // Here you would implement the logic to save the invoice data in your database
            // $order->update(['invoice_id' => $result['data']['uuid'], 'invoiced' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Invoice generated successfully',
                'data' => $result['data'],
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Error generating invoice',
                'errors' => $result['errors'],
            ], 500);
        }
    }

    /**
     * Get available invoicing credits
     *
     * @OA\Get(
     *     path="/api/invoicing/credits",
     *     tags={"Invoicing"},
     *     summary="Get available credits",
     *     description="Gets the available credits for generating invoices",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Credits retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="credits", type="integer", example=1000)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error retrieving credits",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error retrieving credits"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function getAvailableCredits()
    {
        $result = $this->invoiceService->getAvailableCredits();

        return response()->json($result);
    }
}
