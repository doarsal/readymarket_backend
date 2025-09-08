<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PaymentResponse;

class SimulateCardData extends Command
{
    protected $signature = 'simulate:card-data {payment-response-id}';
    protected $description = 'Simulate card data for testing purposes';

    public function handle()
    {
        $paymentResponseId = $this->argument('payment-response-id');

        $paymentResponse = PaymentResponse::find($paymentResponseId);

        if (!$paymentResponse) {
            $this->error("PaymentResponse {$paymentResponseId} no encontrado");
            return 1;
        }

        // Simular datos de tarjeta
        $paymentResponse->update([
            'card_last_four' => '1234',
            'card_name' => 'USUARIO DE PRUEBA',
            'card_type' => 'AMEX'
        ]);

        $this->info("Datos de tarjeta simulados para PaymentResponse {$paymentResponseId}");

        // Mostrar información
        $cardInfo = $paymentResponse->getCardInfo();
        $this->info("Tarjeta Enmascarada: " . $cardInfo['masked_number']);
        $this->info("Texto Display: " . $cardInfo['display_text']);

        return 0;
    }
}
