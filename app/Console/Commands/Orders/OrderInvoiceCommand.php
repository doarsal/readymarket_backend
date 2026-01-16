<?php

namespace App\Console\Commands\Orders;

use App\Models\Order;
use App\Services\InvoiceService;
use Exception;
use Illuminate\Console\Command;

class OrderInvoiceCommand extends Command
{
    protected $signature   = 'order:invoice {orderId}';
    protected $description = 'Generate Order Invoice';

    public function handle(): void
    {
        $orderId = $this->argument('orderId');

        $order = Order::with(['user', 'items'])->findOrFail($orderId);
        $order = $order->load(['billingInformation.taxRegime', 'billingInformation.cfdiUsage']);

        $billing = $order->billingInformation;

        $receiverData = [
            'rfc'         => $billing->rfc,
            'name'        => $billing->organization,
            'postal_code' => $billing->postal_code,
            'tax_regime'  => $billing->taxRegime ? $billing->taxRegime->sat_code : '616',
            'cfdi_use'    => $billing->cfdiUsage ? $billing->cfdiUsage->code : 'S01',
        ];

        $this->info("==== PROCESANDO ORDER {$orderId} =====");
        $this->table(['Campo', 'Valor'], collect($receiverData)->map(fn($value, $key) => [$key, $value])->toArray());

        try {
            $invoiceService = new InvoiceService();
            $invoiceService->generateInvoiceFromOrder($order, $receiverData, false);
            $this->info("Procesado correctamente");
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
