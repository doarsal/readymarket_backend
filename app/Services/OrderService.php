<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentResponse;
use App\Models\PaymentSession;
use App\Models\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    /**
     * Crea una orden desde un carrito después de un pago exitoso
     */
    public function createOrderFromCart(Cart $cart, PaymentResponse $paymentResponse, ?PaymentSession $paymentSession = null): Order
    {
        return DB::transaction(function () use ($cart, $paymentResponse, $paymentSession) {

            Log::info('Creando orden desde carrito', [
                'cart_id' => $cart->id,
                'payment_response_id' => $paymentResponse->id,
                'transaction_reference' => $paymentResponse->transaction_reference,
                'payment_session_id' => $paymentSession?->id,
                'billing_information_id' => $paymentSession?->billing_information_id,
                'microsoft_account_id' => $paymentSession?->microsoft_account_id
            ]);

            // Crear la orden
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $cart->user_id,
                'cart_id' => $cart->id,
                'store_id' => $cart->store_id ?? 1, // Default store si es NULL
                'billing_information_id' => $paymentSession?->billing_information_id,
                'microsoft_account_id' => $paymentSession?->microsoft_account_id,
                'status' => 'processing', // Cambiado de pending a processing porque ya se pagó
                'payment_status' => 'paid',
                'subtotal' => $cart->subtotal,
                'tax_amount' => $cart->tax_amount,
                'total_amount' => $cart->total_amount,
                'currency_id' => $cart->currency_id ?? 1, // Default currency (MXN) si es NULL
                'exchange_rate' => 1.0000, // Asumiendo MXN por defecto
                'exchange_rate_date' => now(),
                'payment_method' => 'credit_card',
                'payment_gateway' => 'mitec',
                'transaction_id' => $paymentResponse->transaction_reference,
                'paid_at' => now(),
                'processed_at' => now(),
                'metadata' => [
                    'mitec_auth_code' => $paymentResponse->auth_code,
                    'mitec_folio' => $paymentResponse->folio_cpagos,
                    'payment_response_id' => $paymentResponse->id
                ]
            ]);

            // Crear los items de la orden desde los items del carrito
            foreach ($cart->items as $cartItem) {
                $this->createOrderItemFromCartItem($order, $cartItem);
            }

            // Actualizar la referencia en PaymentResponse
            $paymentResponse->update(['order_id' => $order->id]);

            // Marcar el carrito como convertido y expirarlo
            $cart->update([
                'status' => 'converted',
                'expires_at' => now() // Expirarlo inmediatamente
            ]);

            Log::info('Orden creada exitosamente', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total_amount' => $order->total_amount
            ]);

            return $order;
        });
    }

    /**
     * Crea un item de orden desde un item de carrito
     */
    protected function createOrderItemFromCartItem(Order $order, $cartItem): OrderItem
    {
        $product = $cartItem->product;

        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $cartItem->product_id,
            'sku_id' => $product->SkuId ?? null,
            'product_title' => $product->ProductTitle ?? 'Producto sin título',
            'product_description' => $product->SkuDescription ?? null,
            'publisher' => $product->Publisher ?? null,
            'segment' => $product->Segment ?? null,
            'market' => $product->Market ?? null,
            'license_duration' => $product->TermDuration ?? null,
            'unit_price' => $cartItem->unit_price,
            'list_price' => $product->UnitPrice ?? $cartItem->unit_price,
            'discount_amount' => 0.00,
            'currency_id' => $order->currency_id,
            'quantity' => $cartItem->quantity,
            'line_total' => $cartItem->total_price,
            'category_name' => $product->category?->name ?? null,
            'category_id_snapshot' => $product->category_id,
            'is_top' => $product->is_top ?? false,
            'is_bestseller' => $product->is_bestseller ?? false,
            'is_novelty' => $product->is_novelty ?? false,
            'is_active' => $product->is_active ?? true,
            'product_metadata' => [
                'product_id_microsoft' => $product->ProductId,
                'billing_plan' => $product->BillingPlan,
                'unit_of_measure' => $product->UnitOfMeasure,
                'tags' => $product->Tags,
                'erp_price' => $product->ERPPrice,
            ],
            'pricing_metadata' => [
                'pricing_tier_min' => $product->PricingTierRangeMin,
                'pricing_tier_max' => $product->PricingTierRangeMax,
                'effective_start_date' => $product->EffectiveStartDate,
                'effective_end_date' => $product->EffectiveEndDate,
            ],
            'fulfillment_status' => 'pending' // Cambiar de 'unfulfilled' a 'pending'
        ]);
    }

    /**
     * Actualiza el estado de pago de una orden
     */
    public function updateOrderPaymentStatus(Order $order, string $status, array $paymentData = []): void
    {
        $updates = ['payment_status' => $status];

        if ($status === 'paid' && !$order->paid_at) {
            $updates['paid_at'] = now();
            $updates['status'] = 'processing'; // Cambiar estado general también
        }

        if ($status === 'failed') {
            $updates['status'] = 'cancelled';
            $updates['cancelled_at'] = now();
            $updates['cancellation_reason'] = 'Payment failed: ' . ($paymentData['error'] ?? 'Unknown error');
        }

        // Agregar metadata de pago
        if (!empty($paymentData)) {
            $existingMetadata = $order->metadata ?? [];
            $updates['metadata'] = array_merge($existingMetadata, $paymentData);
        }

        $order->update($updates);

        Log::info('Estado de pago actualizado', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'payment_status' => $status,
            'general_status' => $updates['status'] ?? $order->status
        ]);
    }

    /**
     * Genera un número de orden único
     */
    protected function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $year = date('Y');
        $month = date('m');

        // Obtener el siguiente número secuencial para este mes
        $lastOrder = Order::where('order_number', 'LIKE', "{$prefix}-{$year}{$month}%")
                          ->orderBy('order_number', 'desc')
                          ->first();

        if ($lastOrder) {
            // Extraer el número secuencial y incrementar
            $lastNumber = (int) substr($lastOrder->order_number, -6);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('%s-%s%s%06d', $prefix, $year, $month, $nextNumber);
    }

    /**
     * Busca una orden por su transaction_reference
     */
    public function findOrderByTransactionReference(string $transactionReference): ?Order
    {
        return Order::where('transaction_id', $transactionReference)->first();
    }

    /**
     * Cancela una orden por pago fallido
     */
    public function cancelOrderForFailedPayment(Order $order, string $reason = 'Payment failed'): void
    {
        $order->update([
            'status' => 'cancelled',
            'payment_status' => 'failed',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason
        ]);

        Log::info('Orden cancelada por pago fallido', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'reason' => $reason
        ]);
    }
}
