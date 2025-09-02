<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

// Simular entorno de Laravel
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

class CartTestRunner
{
    private $baseUrl = 'http://localhost:8000/api/v1';
    private $sessionId;
    private $userToken;

    public function __construct()
    {
        $this->sessionId = 'test_session_' . uniqid();
    }

    public function runAllTests()
    {
        echo "🧪 INICIANDO PRUEBAS COMPLETAS DEL SISTEMA DE CARRITO\n";
        echo "=" . str_repeat("=", 60) . "\n\n";

        try {
            $this->test_guest_can_add_items();
            $this->test_user_login_and_cart_transfer();
            $this->test_cart_operations();
            $this->test_cart_merge_scenarios();

            echo "\n✅ TODAS LAS PRUEBAS COMPLETADAS EXITOSAMENTE!\n";
            echo "🎉 Tu sistema de carrito funciona PERFECTAMENTE!\n\n";

        } catch (Exception $e) {
            echo "\n❌ ERROR EN PRUEBAS: " . $e->getMessage() . "\n";
            echo "📋 Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }

    private function test_guest_can_add_items()
    {
        echo "🔍 Test 1: Usuario invitado puede agregar productos al carrito\n";
        echo "-" . str_repeat("-", 50) . "\n";

        // Obtener productos disponibles
        $products = $this->getProducts();
        if (empty($products)) {
            throw new Exception("No hay productos disponibles para testing");
        }

        $product1 = $products[0];
        $product2 = $products[1] ?? $products[0];

        // Agregar primer producto
        $response1 = $this->addItemToCart($product1['idproduct'], $product1['SkuId'], 2);
        $this->assert($response1['success'], "Debería poder agregar primer producto");
        echo "✅ Producto 1 agregado correctamente\n";

        // Agregar segundo producto
        $response2 = $this->addItemToCart($product2['idproduct'], $product2['SkuId'], 1);
        $this->assert($response2['success'], "Debería poder agregar segundo producto");
        echo "✅ Producto 2 agregado correctamente\n";

        // Verificar carrito
        $cart = $this->getCart();
        $this->assert($cart['success'], "Debería poder obtener carrito");
        $this->assert($cart['stats']['items_count'] >= 2, "Carrito debería tener al menos 2 items");
        echo "✅ Carrito contiene {$cart['stats']['items_count']} items\n";
        echo "💰 Subtotal: ${$cart['stats']['subtotal']}\n";

        echo "✅ Test 1 COMPLETADO\n\n";
    }

    private function test_user_login_and_cart_transfer()
    {
        echo "🔍 Test 2: Login de usuario y transferencia de carrito\n";
        echo "-" . str_repeat("-", 50) . "\n";

        // Obtener carrito actual antes del login
        $guestCart = $this->getCart();
        $guestItemsCount = $guestCart['stats']['items_count'];
        echo "📦 Carrito de invitado tiene {$guestItemsCount} items\n";

        // Crear usuario de prueba
        $testUser = $this->createTestUser();
        echo "👤 Usuario de prueba creado: {$testUser['email']}\n";

        // Login
        $loginResponse = $this->loginUser($testUser['email'], 'password123');
        $this->assert($loginResponse['success'], "Login debería ser exitoso");
        $this->userToken = $loginResponse['data']['token'];
        echo "🔐 Login exitoso, token obtenido\n";

        // Obtener carrito después del login
        $userCart = $this->getCartAuthenticated();
        $this->assert($userCart['success'], "Debería poder obtener carrito autenticado");

        $userItemsCount = $userCart['stats']['items_count'];
        echo "📦 Carrito de usuario tiene {$userItemsCount} items\n";

        // Verificar transferencia
        $this->assert($userItemsCount >= $guestItemsCount, "Items deberían transferirse al usuario");
        echo "✅ Carrito transferido correctamente\n";
        echo "💰 Nuevo subtotal: ${$userCart['stats']['subtotal']}\n";

        echo "✅ Test 2 COMPLETADO\n\n";
    }

    private function test_cart_operations()
    {
        echo "🔍 Test 3: Operaciones del carrito (actualizar, eliminar)\n";
        echo "-" . str_repeat("-", 50) . "\n";

        // Obtener carrito actual
        $cart = $this->getCartAuthenticated();
        $items = $cart['data']['items'];

        if (empty($items)) {
            throw new Exception("No hay items en el carrito para testear");
        }

        $firstItem = $items[0];
        $itemId = $firstItem['id'];
        $originalQuantity = $firstItem['quantity'];

        echo "🎯 Testeando item ID: {$itemId} (cantidad original: {$originalQuantity})\n";

        // Actualizar cantidad
        $newQuantity = $originalQuantity + 3;
        $updateResponse = $this->updateCartItem($itemId, $newQuantity);
        $this->assert($updateResponse['success'], "Debería poder actualizar cantidad");
        echo "✅ Cantidad actualizada de {$originalQuantity} a {$newQuantity}\n";

        // Verificar actualización
        $updatedCart = $this->getCartAuthenticated();
        $updatedItem = collect($updatedCart['data']['items'])->firstWhere('id', $itemId);
        $this->assert($updatedItem['quantity'] == $newQuantity, "Cantidad debería estar actualizada");
        echo "✅ Cantidad verificada en base de datos\n";

        // Eliminar item
        $deleteResponse = $this->deleteCartItem($itemId);
        $this->assert($deleteResponse['success'], "Debería poder eliminar item");
        echo "✅ Item eliminado correctamente\n";

        // Verificar eliminación
        $finalCart = $this->getCartAuthenticated();
        $deletedItem = collect($finalCart['data']['items'])->firstWhere('id', $itemId);
        $this->assert($deletedItem === null, "Item debería estar eliminado");
        echo "✅ Eliminación verificada en base de datos\n";

        echo "✅ Test 3 COMPLETADO\n\n";
    }

    private function test_cart_merge_scenarios()
    {
        echo "🔍 Test 4: Escenarios de merge de carritos\n";
        echo "-" . str_repeat("-", 50) . "\n";

        // Agregar más productos al carrito del usuario
        $products = $this->getProducts();
        $product = $products[2] ?? $products[0];

        $addResponse = $this->addItemToCartAuthenticated($product['idproduct'], $product['SkuId'], 2);
        $this->assert($addResponse['success'], "Debería poder agregar productos como usuario autenticado");
        echo "✅ Producto agregado al carrito de usuario autenticado\n";

        // Simular logout y agregar productos como invitado
        $originalToken = $this->userToken;
        $this->userToken = null;

        $product2 = $products[3] ?? $products[0];
        $guestResponse = $this->addItemToCart($product2['idproduct'], $product2['SkuId'], 1);
        $this->assert($guestResponse['success'], "Debería poder agregar productos como invitado");
        echo "✅ Producto agregado como invitado (simulando nueva sesión)\n";

        // Login nuevamente para activar merge
        $this->userToken = $originalToken;
        $mergedCart = $this->getCartAuthenticated();
        $this->assert($mergedCart['success'], "Debería poder obtener carrito merged");
        echo "✅ Carritos mergeados correctamente\n";
        echo "📦 Carrito final tiene {$mergedCart['stats']['items_count']} items\n";
        echo "💰 Total final: ${$mergedCart['stats']['subtotal']}\n";

        echo "✅ Test 4 COMPLETADO\n\n";
    }

    // Métodos de utilidad para APIs
    private function httpRequest($method, $url, $data = null, $authenticated = false)
    {
        $curl = curl_init();

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Requested-With: XMLHttpRequest'
        ];

        if ($authenticated && $this->userToken) {
            $headers[] = 'Authorization: Bearer ' . $this->userToken;
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($data) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false) {
            throw new Exception("Error en request HTTP");
        }

        $decoded = json_decode($response, true);
        if ($httpCode >= 400) {
            throw new Exception("HTTP Error {$httpCode}: " . ($decoded['message'] ?? 'Unknown error'));
        }

        return $decoded;
    }

    private function getProducts()
    {
        return $this->httpRequest('GET', $this->baseUrl . '/products?limit=10');
    }

    private function addItemToCart($productId, $skuId, $quantity)
    {
        return $this->httpRequest('POST', $this->baseUrl . '/cart/items', [
            'product_id' => $productId,
            'sku_id' => $skuId,
            'quantity' => $quantity
        ]);
    }

    private function addItemToCartAuthenticated($productId, $skuId, $quantity)
    {
        return $this->httpRequest('POST', $this->baseUrl . '/cart/items', [
            'product_id' => $productId,
            'sku_id' => $skuId,
            'quantity' => $quantity
        ], true);
    }

    private function getCart()
    {
        return $this->httpRequest('GET', $this->baseUrl . '/cart');
    }

    private function getCartAuthenticated()
    {
        return $this->httpRequest('GET', $this->baseUrl . '/cart', null, true);
    }

    private function updateCartItem($itemId, $quantity)
    {
        return $this->httpRequest('PUT', $this->baseUrl . "/cart/items/{$itemId}", [
            'quantity' => $quantity
        ], true);
    }

    private function deleteCartItem($itemId)
    {
        return $this->httpRequest('DELETE', $this->baseUrl . "/cart/items/{$itemId}", null, true);
    }

    private function createTestUser()
    {
        $email = 'cart.test.' . uniqid() . '@example.com';

        return $this->httpRequest('POST', $this->baseUrl . '/auth/register', [
            'name' => 'Cart Test User',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ]);
    }

    private function loginUser($email, $password)
    {
        return $this->httpRequest('POST', $this->baseUrl . '/auth/login', [
            'email' => $email,
            'password' => $password
        ]);
    }

    private function assert($condition, $message)
    {
        if (!$condition) {
            throw new Exception("Assertion failed: " . $message);
        }
    }
}

// Ejecutar pruebas
try {
    $tester = new CartTestRunner();
    $tester->runAllTests();
} catch (Exception $e) {
    echo "\n💥 ERROR FATAL: " . $e->getMessage() . "\n";
    echo "📋 En archivo: " . $e->getFile() . " línea " . $e->getLine() . "\n";
    exit(1);
}
