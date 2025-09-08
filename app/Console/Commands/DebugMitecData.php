<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PaymentResponse;

class DebugMitecData extends Command
{
    protected $signature = 'debug:mitec-data';
    protected $description = 'Debug MITEC data to see what card info is being sent';

    public function handle()
    {
        $this->info("=== DEBUGGING MITEC DATA ===");

        // Obtener las últimas 3 respuestas de pago
        $responses = PaymentResponse::orderBy('created_at', 'desc')->take(3)->get();

        foreach ($responses as $response) {
            $this->info("\n--- PaymentResponse ID: {$response->id} ---");
            $this->info("Transaction: {$response->transaction_reference}");
            $this->info("Created: {$response->created_at}");

            if ($response->metadata && isset($response->metadata['all_parsed_data'])) {
                $parsedData = $response->metadata['all_parsed_data'];

                $this->info("CC Number (raw): " . ($parsedData['cc_number'] ?? 'NOT_FOUND'));
                $this->info("CC Name (raw): " . ($parsedData['cc_name'] ?? 'NOT_FOUND'));
                $this->info("CC Type (raw): " . ($parsedData['cc_type'] ?? 'NOT_FOUND'));

                // Mostrar todos los campos que contienen "cc" o "card"
                $this->info("Campos relacionados con tarjeta:");
                foreach ($parsedData as $key => $value) {
                    if (stripos($key, 'cc') !== false || stripos($key, 'card') !== false) {
                        $this->info("  {$key}: {$value}");
                    }
                }

                // Mostrar algunos campos más para contexto
                $this->info("Otros campos importantes:");
                $this->info("  payment_response: " . ($parsedData['payment_response'] ?? 'NOT_FOUND'));
                $this->info("  payment_auth: " . ($parsedData['payment_auth'] ?? 'NOT_FOUND'));

            } else {
                $this->error("No hay metadata o parsed_data para esta respuesta");
            }
        }

        return 0;
    }
}
