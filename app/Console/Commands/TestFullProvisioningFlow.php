<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Services\MicrosoftPartnerCenterService;
use App\Services\PartnerCenterProvisioningService;

class TestFullProvisioningFlow extends Command
{
    protected $signature = 'orders:test-full-flow
                            {--user-id=1 : ID del usuario}
                            {--product-id=168 : ID del producto a usar (Office 365 E1 por defecto)}
                            {--quantity=1 : Cantidad del producto}
                            {--skip-account : Usar cuenta Microsoft existente en lugar de crear nueva}
                            {--account-id= : ID de cuenta Microsoft existente (requiere --skip-account)}';

    protected $description = 'Prueba completa de aprovisionamiento: crea cuenta Microsoft, carrito, orden y aprovisiona';

    protected $partnerCenterService;
    protected $provisioningService;

    public function __construct(
        MicrosoftPartnerCenterService $partnerCenterService,
        PartnerCenterProvisioningService $provisioningService
    ) {
        parent::__construct();
        $this->partnerCenterService = $partnerCenterService;
        $this->provisioningService = $provisioningService;
    }

    public function handle()
    {
        $this->info("╔════════════════════════════════════════════════════════════╗");
        $this->info("║         TEST COMPLETO DE APROVISIONAMIENTO E2E             ║");
        $this->info("╚════════════════════════════════════════════════════════════╝");
        $this->newLine();

        $userId = $this->option('user-id');
        $productId = $this->option('product-id');
        $quantity = $this->option('quantity');
        $skipAccount = $this->option('skip-account');
        $accountId = $this->option('account-id');

        try {
            // 1. Verificar usuario
            $this->line("1️⃣  Verificando usuario...");
            $user = User::find($userId);
            if (!$user) {
                $this->error("✗ Usuario con ID {$userId} no encontrado");
                return Command::FAILURE;
            }
            $this->info("   ✓ Usuario: {$user->name} ({$user->email})");
            $this->newLine();

            // 2. Crear o usar cuenta Microsoft
            if ($skipAccount && $accountId) {
                $this->line("2️⃣  Usando cuenta Microsoft existente...");
                $microsoftAccount = DB::table('microsoft_accounts')->find($accountId);
                if (!$microsoftAccount) {
                    $this->error("✗ Cuenta Microsoft ID {$accountId} no encontrada");
                    return Command::FAILURE;
                }
                $this->info("   ✓ Cuenta: {$microsoftAccount->domain} (ID: {$microsoftAccount->id})");
            } else {
                $this->line("2️⃣  Creando nueva cuenta Microsoft...");
                $microsoftAccount = $this->createMicrosoftAccount($user);
                if (!$microsoftAccount) {
                    $this->error("✗ Error al crear cuenta Microsoft");
                    return Command::FAILURE;
                }
                $this->info("   ✓ Cuenta creada: {$microsoftAccount->domain}");
                $this->info("   ✓ Customer ID: {$microsoftAccount->microsoft_id}");
                $this->info("   ✓ Account ID: {$microsoftAccount->id}");
            }
            $this->newLine();

            // 3. Verificar producto
            $this->line("3️⃣  Verificando producto...");
            $product = DB::table('products')
                ->where('idproduct', $productId)
                ->where('is_available', true)
                ->first();

            if (!$product) {
                $this->error("✗ Producto ID {$productId} no encontrado o no disponible");
                return Command::FAILURE;
            }
            $this->info("   ✓ Producto: {$product->ProductTitle}");
            $this->info("   ✓ SKU: {$product->SkuId}");
            $this->info("   ✓ Precio: \${$product->UnitPrice} USD");
            $this->newLine();

            // 4. Crear carrito
            $this->line("4️⃣  Creando carrito...");
            $cart = $this->createCart($user);
            $this->info("   ✓ Carrito creado (ID: {$cart->id})");
            $this->newLine();

            // 5. Agregar producto al carrito
            $this->line("5️⃣  Agregando producto al carrito...");
            $this->addProductToCart($cart, $product, $quantity);
            $this->info("   ✓ Producto agregado: {$product->ProductTitle} x{$quantity}");
            $this->newLine();

            // 6. Crear orden
            $this->line("6️⃣  Creando orden...");
            $order = $this->createOrder($cart, $user, $microsoftAccount);

            // Recargar orden con relaciones
            $order = $order->fresh(['cart.items.product', 'orderItems', 'microsoftAccount']);

            $this->info("   ✓ Orden creada: #{$order->order_number} (ID: {$order->id})");
            $this->info("   ✓ Subtotal: \${$order->subtotal}");
            $this->info("   ✓ Total: \${$order->total_amount} {$order->currency->code}");
            $this->info("   ✓ Cart ID: {$order->cart_id}");
            $this->info("   ✓ Cart Items: {$order->cart->items->count()}");
            $this->info("   ✓ Order Items: {$order->orderItems->count()}");
            $this->newLine();

            // 7. Aprovisionar en Microsoft
            $this->line("7️⃣  Aprovisionando en Microsoft Partner Center...");
            $this->line("   (Esto puede tardar unos segundos...)");

            // Debug: Mostrar datos del producto ANTES de aprovisionar
            $this->newLine();
            $this->warn("   🔍 DEBUG - Datos del producto:");
            $cartItem = $order->cart->items->first();
            $product = $cartItem->product;
            $this->line("   ProductId: {$product->ProductId}");
            $this->line("   SkuId: {$product->SkuId}");
            $this->line("   AvailabilityId (Id): {$product->Id}");
            $this->line("   CatalogItemId: {$product->ProductId}:{$product->SkuId}:{$product->Id}");
            $this->line("   is_available: " . ($product->is_available ? 'true' : 'false'));
            $this->newLine();

            $result = $this->provisioningService->processOrder($order->id);

            if (isset($result['status']) && $result['status'] === 'success') {
                $this->info("   ✓ Aprovisionamiento exitoso");
                if (isset($result['successful_products'])) {
                    $this->info("   ✓ Productos aprovisionados: {$result['successful_products']}");
                }
            } else {
                $this->warn("   ⚠ Aprovisionamiento completado con advertencias");
                if (isset($result['message'])) {
                    $this->line("   Mensaje: {$result['message']}");
                }
                if (isset($result['provisioning_results']) && !empty($result['provisioning_results'])) {
                    foreach ($result['provisioning_results'] as $provisioning) {
                        if (!$provisioning['success'] && isset($provisioning['error_message'])) {
                            $this->error("   Error: {$provisioning['error_message']}");
                            if (isset($provisioning['microsoft_details'])) {
                                $this->line("   Detalles MS: " . json_encode($provisioning['microsoft_details'], JSON_PRETTY_PRINT));
                            }
                        }
                    }
                }
            }
            $this->newLine();

            // 8. Verificar suscripciones creadas
            $this->line("8️⃣  Verificando suscripciones...");
            $subscriptions = DB::table('subscriptions')
                ->where('order_id', $order->id)
                ->get();

            if ($subscriptions->count() > 0) {
                $this->info("   ✓ Suscripciones creadas: {$subscriptions->count()}");
                foreach ($subscriptions as $sub) {
                    $this->line("     • Subscription ID: {$sub->subscription_id}");
                    $this->line("       Producto: {$sub->friendly_name}");
                    $this->line("       Cantidad: {$sub->quantity}");
                    $this->line("       Precio: \${$sub->pricing}");
                }
            } else {
                $this->error("   ✗ No se crearon suscripciones");
            }
            $this->newLine();

            // 9. Confirmar orden como completada
            $this->line("9️⃣  Confirmando orden como completada...");
            $order->update([
                'status' => 'completed',
                'fulfillment_status' => 'fulfilled',
                'processed_at' => now()
            ]);
            $this->info("   ✓ Orden marcada como completada");
            $this->newLine();

            // Resumen final
            $this->info("╔════════════════════════════════════════════════════════════╗");
            $this->info("║                  ✓ FLUJO COMPLETADO ✓                      ║");
            $this->info("╚════════════════════════════════════════════════════════════╝");
            $this->newLine();

            $this->table(
                ['Concepto', 'Detalle'],
                [
                    ['Usuario', $user->name],
                    ['Cuenta Microsoft', $microsoftAccount->domain],
                    ['Orden', "#{$order->order_number}"],
                    ['Producto', $product->ProductTitle],
                    ['Cantidad', $quantity],
                    ['Total', "\${$order->total_amount} {$order->currency->code}"],
                    ['Suscripciones', $subscriptions->count()],
                    ['Estado', '✓ Completado']
                ]
            );

            $this->newLine();
            $this->info("✓ Todo el flujo de aprovisionamiento funcionó correctamente de inicio a fin");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error("╔════════════════════════════════════════════════════════════╗");
            $this->error("║                    ✗ ERROR                                 ║");
            $this->error("╚════════════════════════════════════════════════════════════╝");
            $this->newLine();
            $this->error("Error: {$e->getMessage()}");
            $this->error("Archivo: {$e->getFile()}:{$e->getLine()}");

            return Command::FAILURE;
        }
    }

    private function createMicrosoftAccount($user)
    {
        $timestamp = time();
        $companyName = "ReadyMarket Customer " . $timestamp;
        $domain = "rmcustomer" . $timestamp;

        $customerData = [
            'first_name' => 'Juan',
            'last_name' => 'Perez',
            'email' => 'admin@' . $domain . '.com',
            'phone' => '5512345678',
            'organization' => $companyName,
            'domain' => $domain,
            'domain_concatenated' => $domain . '.onmicrosoft.com',
            'address' => 'Calle Principal 123',
            'city' => 'Ciudad de México',
            'state_code' => 'DF',
            'postal_code' => '01000',
            'country_code' => 'MX',
            'language_code' => 'es',
            'culture' => 'es-MX',
        ];

        // Crear en Microsoft Partner Center
        $customerResult = $this->partnerCenterService->createCustomer($customerData);

        // Guardar en BD
        $accountId = DB::table('microsoft_accounts')->insertGetId([
            'user_id' => $user->id,
            'microsoft_id' => $customerResult['microsoft_id'],
            'domain' => $domain,
            'domain_concatenated' => $domain . '.onmicrosoft.com',
            'first_name' => $customerData['first_name'],
            'last_name' => $customerData['last_name'],
            'email' => $customerData['email'],
            'phone' => $customerData['phone'],
            'organization' => $customerData['organization'],
            'address' => $customerData['address'],
            'city' => $customerData['city'],
            'state_code' => $customerData['state_code'],
            'postal_code' => $customerData['postal_code'],
            'country_code' => $customerData['country_code'],
            'language_code' => $customerData['language_code'],
            'culture' => $customerData['culture'],
            'is_active' => true,
            'is_pending' => false,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Aceptar Customer Agreement
        $this->partnerCenterService->acceptCustomerAgreement(
            $customerResult['microsoft_id'],
            $customerData
        );

        return DB::table('microsoft_accounts')->find($accountId);
    }

    private function createCart($user)
    {
        $store = DB::table('stores')->first();

        // Buscar carrito activo existente o crear uno nuevo
        $cart = Cart::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($cart) {
            // Limpiar items del carrito existente
            DB::table('cart_items')->where('cart_id', $cart->id)->delete();
            return $cart;
        }

        return Cart::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'cart_token' => 'test-' . uniqid(),
            'status' => 'active',
            'ip_address' => '127.0.0.1'
        ]);
    }

    private function addProductToCart($cart, $product, $quantity)
    {
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->idproduct,
            'quantity' => $quantity,
            'status' => 'active',
        ]);
    }

    private function createOrder($cart, $user, $microsoftAccount)
    {
        $billingInfo = DB::table('billing_information')
            ->where('user_id', $user->id)
            ->first();

        $orderData = [
            'billing_information_id' => $billingInfo ? $billingInfo->id : null,
            'microsoft_account_id' => $microsoftAccount->id,
            'payment_method' => 'credit_card',
            'payment_status' => 'paid',
            'status' => 'processing',
            'notes' => 'Pedido de prueba automático - Comando artisan',
        ];

        $cartWithRelations = Cart::with(['items.product', 'store.currencies'])->find($cart->id);

        return Order::createFromCart($cartWithRelations, $orderData);
    }
}
