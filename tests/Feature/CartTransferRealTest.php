<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Store;
use App\Models\Currency;
use App\Services\CartService;
use Tests\TestCase;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Artisan;

class CartTransferRealTest extends TestCase
{
    protected CartService $cartService;
    protected User $testUser;
    protected array $testProducts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartService = app(CartService::class);

        // Usar base de datos real MySQL en lugar de SQLite
        config(['database.default' => 'mysql']);

        $this->prepareTestData();
    }

    protected function prepareTestData(): void
    {
        // Limpiar datos de prueba anteriores
        $this->cleanupTestData();

        // Crear usuario de prueba
        $this->testUser = User::create([
            'name' => 'Cart Test User',
            'email' => 'cart.test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'is_verified' => true,
        ]);

        // Obtener productos existentes para testing
        $this->testProducts = Product::where('prod_active', 1)
                                    ->take(3)
                                    ->get()
                                    ->toArray();

        if (empty($this->testProducts)) {
            $this->markTestSkipped('No products available for testing. Run seeders first.');
        }
    }

    protected function cleanupTestData(): void
    {
        // Limpiar carritos de prueba
        Cart::whereNotNull('session_id')
            ->where('session_id', 'like', 'test_%')
            ->delete();

        CartItem::whereIn('cart_id', Cart::whereNotNull('session_id')->pluck('id'))->delete();

        // Limpiar usuario de prueba si existe
        User::where('email', 'cart.test@example.com')->delete();
    }

    public function test_guest_can_add_items_to_cart(): void
    {
        echo "\n🧪 Testing: Guest can add items to cart\n";

        // Simular nueva sesión
        Session::flush();
        Session::regenerate();

        $product = $this->testProducts[0];

        $response = $this->postJson('/api/v1/cart/items', [
            'product_id' => $product['idproduct'],
            'sku_id' => $product['SkuId'],
            'quantity' => 2,
        ]);

        $this->assertTrue($response->status() === 201, 'Guest should be able to add items to cart');

        $responseData = $response->json();
        $this->assertTrue($responseData['success'], 'Response should be successful');
        $this->assertEquals('Producto agregado al carrito.', $responseData['message']);

        // Verificar que se creó carrito de sesión
        $sessionId = session()->getId();
        $cart = Cart::where('session_id', $sessionId)
                   ->where('status', 'active')
                   ->first();

        $this->assertNotNull($cart, 'Session cart should be created');
        $this->assertNull($cart->user_id, 'Cart should not have user_id');
        $this->assertEquals(1, $cart->activeItems()->count(), 'Cart should have 1 item');

        echo "✅ Guest can add items to cart - PASSED\n";
    }

    public function test_cart_transfer_when_user_logs_in(): void
    {
        echo "\n🧪 Testing: Cart transfer when user logs in\n";

        // Paso 1: Crear carrito como invitado
        Session::flush();
        Session::regenerate();

        $product1 = $this->testProducts[0];
        $product2 = $this->testProducts[1];

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product1['idproduct'],
            'sku_id' => $product1['SkuId'],
            'quantity' => 2,
        ])->assertStatus(201);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product2['idproduct'],
            'sku_id' => $product2['SkuId'],
            'quantity' => 1,
        ])->assertStatus(201);

        // Verificar carrito de sesión
        $sessionId = session()->getId();
        $sessionCart = Cart::where('session_id', $sessionId)
                          ->where('status', 'active')
                          ->first();

        $this->assertNotNull($sessionCart, 'Session cart should exist');
        $this->assertEquals(2, $sessionCart->activeItems()->count(), 'Session cart should have 2 items');

        // Paso 2: Usuario se loguea
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->testUser->email,
            'password' => 'password',
        ]);

        $this->assertTrue($loginResponse->status() === 200, 'Login should be successful');
        $token = $loginResponse->json('data.token');

        // Paso 3: Hacer request autenticado para activar transferencia
        $cartResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/cart');

        $this->assertTrue($cartResponse->status() === 200, 'Cart retrieval should be successful');

        // Paso 4: Verificar transferencia
        $userCart = Cart::where('user_id', $this->testUser->id)
                       ->where('status', 'active')
                       ->first();

        $this->assertNotNull($userCart, 'User should have an active cart');
        $this->assertEquals(2, $userCart->activeItems()->count(), 'User cart should have transferred items');

        // Verificar productos específicos
        $userItems = $userCart->activeItems;
        $productIds = $userItems->pluck('product_id')->toArray();

        $this->assertContains($product1['idproduct'], $productIds, 'Product 1 should be in user cart');
        $this->assertContains($product2['idproduct'], $productIds, 'Product 2 should be in user cart');

        echo "✅ Cart transfer when user logs in - PASSED\n";
    }

    public function test_cart_merge_when_user_already_has_cart(): void
    {
        echo "\n🧪 Testing: Cart merge when user already has cart\n";

        $product1 = $this->testProducts[0];
        $product2 = $this->testProducts[1];

        // Paso 1: Usuario ya tiene carrito
        $existingCart = Cart::create([
            'user_id' => $this->testUser->id,
            'status' => 'active',
        ]);

        CartItem::create([
            'cart_id' => $existingCart->id,
            'product_id' => $product1['idproduct'],
            'sku_id' => $product1['SkuId'],
            'quantity' => 1,
            'unit_price' => $product1['UnitPrice'],
            'total_price' => $product1['UnitPrice'],
            'status' => 'active',
        ]);

        // Paso 2: Crear carrito de sesión
        Session::flush();
        Session::regenerate();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product2['idproduct'],
            'sku_id' => $product2['SkuId'],
            'quantity' => 2,
        ])->assertStatus(201);

        // Paso 3: Login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->testUser->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.token');

        // Paso 4: Activar merge
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/cart')->assertStatus(200);

        // Paso 5: Verificar merge
        $existingCart->refresh();
        $this->assertEquals('active', $existingCart->status, 'User cart should remain active');
        $this->assertEquals(2, $existingCart->activeItems()->count(), 'Cart should have merged items');

        echo "✅ Cart merge when user already has cart - PASSED\n";
    }

    public function test_cart_operations_work_correctly(): void
    {
        echo "\n🧪 Testing: Cart operations work correctly\n";

        $product = $this->testProducts[0];

        // Login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->testUser->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.token');
        $headers = ['Authorization' => 'Bearer ' . $token];

        // Agregar producto
        $addResponse = $this->withHeaders($headers)
                           ->postJson('/api/v1/cart/items', [
                               'product_id' => $product['idproduct'],
                               'sku_id' => $product['SkuId'],
                               'quantity' => 2,
                           ]);

        $this->assertTrue($addResponse->status() === 201, 'Product should be added successfully');
        $itemId = $addResponse->json('data.id');

        // Actualizar cantidad
        $updateResponse = $this->withHeaders($headers)
                              ->putJson("/api/v1/cart/items/{$itemId}", [
                                  'quantity' => 5,
                              ]);

        $this->assertTrue($updateResponse->status() === 200, 'Item should be updated successfully');

        // Verificar actualización
        $item = CartItem::find($itemId);
        $this->assertEquals(5, $item->quantity, 'Quantity should be updated');

        // Eliminar item
        $deleteResponse = $this->withHeaders($headers)
                              ->deleteJson("/api/v1/cart/items/{$itemId}");

        $this->assertTrue($deleteResponse->status() === 200, 'Item should be deleted successfully');

        // Verificar eliminación
        $item = CartItem::find($itemId);
        $this->assertNull($item, 'Item should be deleted');

        echo "✅ Cart operations work correctly - PASSED\n";
    }

    public function test_cart_totals_calculate_correctly(): void
    {
        echo "\n🧪 Testing: Cart totals calculate correctly\n";

        $product1 = $this->testProducts[0];
        $product2 = $this->testProducts[1];

        // Login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->testUser->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.token');
        $headers = ['Authorization' => 'Bearer ' . $token];

        // Agregar productos
        $this->withHeaders($headers)
             ->postJson('/api/v1/cart/items', [
                 'product_id' => $product1['idproduct'],
                 'sku_id' => $product1['SkuId'],
                 'quantity' => 2,
             ])->assertStatus(201);

        $this->withHeaders($headers)
             ->postJson('/api/v1/cart/items', [
                 'product_id' => $product2['idproduct'],
                 'sku_id' => $product2['SkuId'],
                 'quantity' => 1,
             ])->assertStatus(201);

        // Obtener carrito y verificar totales
        $cartResponse = $this->withHeaders($headers)->getJson('/api/v1/cart');

        $this->assertTrue($cartResponse->status() === 200, 'Cart should be retrieved successfully');

        $stats = $cartResponse->json('stats');
        $this->assertGreaterThan(0, $stats['subtotal'], 'Subtotal should be greater than 0');
        $this->assertEquals(3, $stats['items_count'], 'Items count should be 3');
        $this->assertEquals(2, $stats['unique_products'], 'Unique products should be 2');

        echo "✅ Cart totals calculate correctly - PASSED\n";
    }

    public function test_complete_shopping_flow(): void
    {
        echo "\n🧪 Testing: Complete shopping flow (Guest → Login → Purchase)\n";

        $product1 = $this->testProducts[0];
        $product2 = $this->testProducts[1];

        // Paso 1: Invitado agrega productos
        Session::flush();
        Session::regenerate();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product1['idproduct'],
            'sku_id' => $product1['SkuId'],
            'quantity' => 2,
        ])->assertStatus(201);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product2['idproduct'],
            'sku_id' => $product2['SkuId'],
            'quantity' => 1,
        ])->assertStatus(201);

        // Verificar carrito de invitado
        $guestCartResponse = $this->getJson('/api/v1/cart');
        $this->assertTrue($guestCartResponse->status() === 200);
        $guestStats = $guestCartResponse->json('stats');
        $this->assertEquals(3, $guestStats['items_count']);

        // Paso 2: Usuario se registra/loguea
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->testUser->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.token');

        // Paso 3: Verificar que el carrito se transfirió
        $userCartResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/cart');

        $this->assertTrue($userCartResponse->status() === 200);
        $userStats = $userCartResponse->json('stats');
        $this->assertEquals(3, $userStats['items_count'], 'Items should be transferred');
        $this->assertEquals(2, $userStats['unique_products'], 'Unique products should be preserved');

        // Paso 4: Usuario puede modificar carrito
        $userCart = $userCartResponse->json('data');
        $firstItem = $userCart['items'][0];

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/v1/cart/items/{$firstItem['id']}", [
            'quantity' => 5,
        ])->assertStatus(200);

        // Paso 5: Verificar totales finales
        $finalCartResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/cart');

        $finalStats = $finalCartResponse->json('stats');
        $this->assertEquals(6, $finalStats['items_count'], 'Updated quantity should be reflected'); // 5 + 1

        echo "✅ Complete shopping flow - PASSED\n";
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }
}
