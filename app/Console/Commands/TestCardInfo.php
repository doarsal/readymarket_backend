<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\PaymentResponse;

class TestCardInfo extends Command
{
    protected $signature = 'test:card-info {--order-id=}';
    protected $description = 'Test card information extraction and display';

    public function handle()
    {
        $orderId = $this->option('order-id');

        if ($orderId) {
            $order = Order::with('paymentResponse')->find($orderId);
            if (!$order) {
                $this->error("Orden {$orderId} no encontrada");
                return 1;
            }
        } else {
            $order = Order::with('paymentResponse')
                ->whereIn('payment_status', ['paid'])
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$order) {
                $this->error('No se encontró ninguna orden pagada');
                return 1;
            }
        }

        $this->info("=== INFORMACIÓN DE ORDEN: {$order->order_number} ===");
        $this->info("Transaction ID: " . ($order->transaction_id ?? 'NULL'));

        // Buscar PaymentResponse por transaction_reference
        $paymentResponse = PaymentResponse::where('transaction_reference', $order->transaction_id)->first();

        if (!$paymentResponse && $order->paymentResponse) {
            $paymentResponse = $order->paymentResponse;
            $this->info("PaymentResponse encontrado via relación");
        } elseif ($paymentResponse) {
            $this->info("PaymentResponse encontrado por transaction_reference");
        } else {
            $this->error("PaymentResponse NO encontrado");

            // Mostrar todas las PaymentResponses recientes para debug
            $this->info("\n=== PaymentResponses recientes ===");
            $recent = PaymentResponse::orderBy('created_at', 'desc')->take(5)->get();
            foreach ($recent as $pr) {
                $this->info("ID: {$pr->id}, Ref: {$pr->transaction_reference}, Card: {$pr->card_last_four}");
            }
            return 1;
        }

        $this->info("\n=== INFORMACIÓN DE PAYMENT RESPONSE ===");
        $this->info("PaymentResponse ID: {$paymentResponse->id}");
        $this->info("Card Last Four (raw): " . ($paymentResponse->card_last_four ?? 'NULL'));
        $this->info("Card Name: " . ($paymentResponse->card_name ?? 'NULL'));
        $this->info("Card Type: " . ($paymentResponse->card_type ?? 'NULL'));

        // Probar método de extracción
        $this->info("\n=== PROBANDO MÉTODOS ===");

        // Simular números de tarjeta para probar
        $testNumbers = [
            '1234567890123456',
            '**** **** **** 5678',
            '************5678',
            'xxxxxxxxxxxx5678',
            '5678',
            null
        ];

        foreach ($testNumbers as $testNumber) {
            $extracted = PaymentResponse::extractLastFourDigits($testNumber);
            $this->info("Input: '{$testNumber}' -> Output: '{$extracted}'");
        }

        if ($paymentResponse->card_last_four) {
            $cardInfo = $paymentResponse->getCardInfo();
            $this->info("\n=== CARD INFO ===");
            $this->info("Masked Number: " . $cardInfo['masked_number']);
            $this->info("Display Text: " . $cardInfo['display_text']);
        }

        return 0;
    }
}
