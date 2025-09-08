<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\PurchaseConfirmationEmailService;
use App\Services\WhatsAppNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestPurchaseConfirmations extends Command
{
    protected $signature = 'test:purchase-confirmations {order_id?}';
    protected $description = 'Test purchase confirmation emails and WhatsApp for an order';

    protected PurchaseConfirmationEmailService $purchaseEmailService;
    protected WhatsAppNotificationService $whatsAppService;

    public function __construct(
        PurchaseConfirmationEmailService $purchaseEmailService,
        WhatsAppNotificationService $whatsAppService
    ) {
        parent::__construct();
        $this->purchaseEmailService = $purchaseEmailService;
        $this->whatsAppService = $whatsAppService;
    }

    public function handle()
    {
        $orderId = $this->argument('order_id');

        if (!$orderId) {
            // Buscar la última orden pagada
            $order = Order::with([
                'user',
                'currency',
                'items.product',
                'billingInformation',
                'microsoftAccount',
                'paymentResponse'
            ])
            ->whereIn('payment_status', ['paid'])
            ->orderBy('created_at', 'desc')
            ->first();

            if (!$order) {
                $this->error('No se encontró ninguna orden pagada');
                return 1;
            }
        } else {
            $order = Order::with([
                'user',
                'currency',
                'items.product',
                'billingInformation',
                'microsoftAccount',
                'paymentResponse'
            ])->find($orderId);

            if (!$order) {
                $this->error("Orden con ID {$orderId} no encontrada");
                return 1;
            }
        }

        $this->info("Probando confirmaciones para orden: {$order->order_number}");
        $this->info("Cliente: {$order->user->email}");
        $this->info("Teléfono: " . ($order->user->phone ?? 'No tiene'));

        // Datos de transacción de prueba
        $paymentData = [
            'reference' => $order->transaction_id ?? 'TEST-REF-' . time(),
            'auth_code' => 'TEST-AUTH-' . rand(100000, 999999),
            'amount' => $order->total_amount,
            'currency' => $order->currency->code ?? 'MXN',
            'processed_at' => $order->paid_at?->toISOString() ?? now()->toISOString()
        ];

        $microsoftAccount = $order->microsoftAccount;

        $this->info("\n=== INFORMACIÓN DE LA ORDEN ===");
        $this->info("Microsoft Account ID: " . ($order->microsoft_account_id ?? 'NULL'));
        $this->info("Microsoft Account Found: " . ($microsoftAccount ? 'SÍ' : 'NO'));
        if ($microsoftAccount) {
            $this->info("Microsoft Domain: " . $microsoftAccount->domain);
            $this->info("Microsoft Email: " . $microsoftAccount->email);
            $this->info("Microsoft Organization: " . $microsoftAccount->organization);
        }

        // Información de tarjeta
        if ($order->paymentResponse && $order->paymentResponse->card_last_four) {
            $cardInfo = $order->paymentResponse->getCardInfo();
            $this->info("Tarjeta: " . $cardInfo['display_text']);
            $this->info("Tarjeta Enmascarada: " . $cardInfo['masked_number']);
        } else {
            $this->info("Información de tarjeta: No disponible");
        }

        $this->info("\n=== ENVIANDO EMAILS ===");

        // Email al cliente
        try {
            $customerEmailSent = $this->purchaseEmailService->sendCustomerConfirmation($order, $microsoftAccount, $paymentData);
            if ($customerEmailSent) {
                $this->info("✅ Email enviado al cliente: {$order->user->email}");
            } else {
                $this->error("❌ Error enviando email al cliente");
            }
        } catch (\Exception $e) {
            $this->error("❌ Error enviando email al cliente: " . $e->getMessage());
        }

        // Email a administradores
        try {
            $adminEmailSent = $this->purchaseEmailService->sendAdminConfirmation($order, $microsoftAccount, $paymentData);
            if ($adminEmailSent) {
                $adminEmails = env('PURCHASE_CONFIRMATION_NOTIFICATION_EMAIL', 'No configurado');
                $this->info("✅ Email enviado a administradores: {$adminEmails}");
            } else {
                $this->error("❌ Error enviando email a administradores");
            }
        } catch (\Exception $e) {
            $this->error("❌ Error enviando email a administradores: " . $e->getMessage());
        }

        $this->info("\n=== ENVIANDO WHATSAPP ===");

        // WhatsApp al cliente
        try {
            if ($order->user->phone) {
                $this->whatsAppService->sendPurchaseConfirmationToCustomer($order, $microsoftAccount, $paymentData);
                $this->info("✅ WhatsApp enviado al cliente: {$order->user->phone}");
            } else {
                $this->warn("⚠️ Cliente no tiene teléfono registrado");
            }
        } catch (\Exception $e) {
            $this->error("❌ Error enviando WhatsApp al cliente: " . $e->getMessage());
        }

        // WhatsApp a administradores
        try {
            $this->whatsAppService->sendPurchaseConfirmationToAdmins($order, $microsoftAccount, $paymentData);
            $adminNumbers = env('WHATSAPP_NOTIFICATION_NUMBER', 'No configurado');
            $this->info("✅ WhatsApp enviado a administradores: {$adminNumbers}");
        } catch (\Exception $e) {
            $this->error("❌ Error enviando WhatsApp a administradores: " . $e->getMessage());
        }

        $this->info("\n=== CONFIGURACIÓN ACTUAL ===");
        $this->info("Emails admin: " . env('PURCHASE_CONFIRMATION_NOTIFICATION_EMAIL', 'NO CONFIGURADO'));
        $this->info("WhatsApp admin: " . env('WHATSAPP_NOTIFICATION_NUMBER', 'NO CONFIGURADO'));
        $this->info("WhatsApp token: " . (env('WHATSAPP_GRAPH_TOKEN') ? 'CONFIGURADO' : 'NO CONFIGURADO'));
        $this->info("Mail config: " . env('MAIL_MAILER', 'NO CONFIGURADO'));

        return 0;
    }
}
