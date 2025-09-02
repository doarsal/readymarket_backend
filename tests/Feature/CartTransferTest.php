<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Store;
use App\Models\Currency;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Session;

class CartTransferTest extends TestCase
{
    use RefreshDatabase;

    protected CartService $cartService;
    protected User $testUser;
    protected Product $testProduct1;
    protected Product $testProduct2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartService = app(CartService::class);

        // Crear datos de prueba
        $this->createTestData();
    }

    protected function createTestData(): void
    {
        // Crear store y currency
        $store = Store::create([
            'name' => 'Test Store',
            'slug' => 'test-store',
            'description' => 'Test store description',
            'status' => 'active'
        ]);

        $currency = Currency::create([
            'name' => 'Peso Mexicano',
            'code' => 'MXN',
            'symbol' => '$',
            'exchange_rate' => 1.0,
            'is_default' => true
        ]);

        // Crear usuario de prueba
        $this->testUser = User::factory()->create([
            'email' => 'test@example.com',
            'name' => 'Test User'
        ]);

        // Crear productos de prueba
        $this->testProduct1 = Product::factory()->create([
            'store_id' => $store->id,
            'UnitPrice' => 99.99,
            'SkuId' => 'TEST-SKU-001',
            'prod_active' => 1
        ]);

        $this->testProduct2 = Product::factory()->create([
            'store_id' => $store->id,
            'UnitPrice' => 149.99,
            'SkuId' => 'TEST-SKU-002',
            'prod_active' => 1
        ]);
    }

    /** @test */
    public function test_guest_can_create_cart_and_add_products()
    {
        // Simular que no hay usuario logueado
        $this->assertGuest();

        // Agregar primer producto al carrito como invitado
        $response1 = $this->postJson('/api/v1/cart/items', [
            'product_id' => $this->testProduct1->idproduct,
            'sku_id' => $this->testProduct1->SkuId,
            'quantity' => 2,
        ]);

        $response1->assertStatus(201)
                  ->assertJson([
                      'success' => true,
                      'message' => 'Producto agregado al carrito.'
                  ]);

        // Agregar segundo producto
        $response2 = $this->postJson('/api/v1/cart/items', [
            'product_id' => $this->testProduct2->idproduct,
            'sku_id' => $this->testProduct2->SkuId,
            'quantity' => 1,
        ]);

        $response2->assertStatus(201);

        // Verificar que se creó carrito de sesión
        $sessionId = session()->getId();

        $this->assertDatabaseHas('carts', [
            'session_id' => $sessionId,
            'user_id' => null,
            'status' => 'active'
        ]);

        // Verificar que ambos productos están en el carrito
        $cart = Cart::where('session_id', $sessionId)->where('status', 'active')->first();
        $this->assertNotNull($cart);
        $this->assertEquals(2, $cart->activeItems()->count());

        // Verificar productos específicos
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $this->testProduct1->idproduct,
            'quantity' => 2,
            'status' => 'active'
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $this->testProduct2->idproduct,
            'quantity' => 1,
            'status' => 'active'
        ]);

        echo "✅ Guest can create cart and add products\n";
    }

    /** @test */
    public function test_cart_transfer_when_user_logs_in()
    {
        // Paso 1: Crear carrito como invitado
        $sessionId = session()->getId();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $this->testProduct1->idproduct,
            'sku_id' => $this->testProduct1->SkuId,
            'quantity' => 3,
        ]);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $this->testProduct2->idproduct,
            'sku_id' => $this->testProduct2->SkuId,
            'quantity' => 2,
        ]);

        // Verificar carrito de sesión creado
        $sessionCart = Cart::where('session_id', $sessionId)->where('status', 'active')->first();
        $this->assertNotNull($sessionCart);
        $this->assertNull($sessionCart->user_id);
        $this->assertEquals(2, $sessionCart->activeItems()->count());

        // Paso 2: Usuario se loguea
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->testUser->email,
            'password' => 'password', // Usuario factory por defecto
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('data.token');

        // Paso 3: Hacer request autenticado para activar la transferencia
        $cartResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/cart');

        $cartResponse->assertStatus(200);

        // Paso 4: Verificar que el carrito se transfirió correctamente
        // El carrito original de sesión debe estar marcado como 'merged' o eliminado
        $sessionCart->refresh();
        $this->assertTrue(in_array($sessionCart->status, ['merged', 'transferred']));

        // Debe existir un carrito activo para el usuario
        $userCart = Cart::where('user_id', $this->testUser->id)
                       ->where('status', 'active')
                       ->first();

        $this->assertNotNull($userCart);
        $this->assertEquals(2, $userCart->activeItems()->count());

        // Verificar que los productos están en el carrito del usuario
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $userCart->id,
            'product_id' => $this->testProduct1->idproduct,
            'quantity' => 3,
            'status' => 'active'
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $userCart->id,
            'product_id' => $this->testProduct2->idproduct,
            'quantity' => 2,
            'status' => 'active'
        ]);

        echo "✅ Cart transfers correctly when user logs in\n";
    }

    /** @test */
    public function test_cart_merge_when_user_already_has_cart()
    {
        // Paso 1: Usuario ya tiene un carrito con productos
        $existingUserCart = Cart::create([
            'user_id' => $this->testUser->id,
            'status' => 'active',
        ]);

        CartItem::create([
            'cart_id' => $existingUserCart->id,
            'product_id' => $this->testProduct1->idproduct,
            'sku_id' => $this->testProduct1->SkuId,
            'quantity' => 1,
            'unit_price' => $this->testProduct1->UnitPrice,
            'total_price' => $this->testProduct1->UnitPrice,
            'status' => 'active',
        ]);

        // Paso 2: Crear carrito de sesión con productos diferentes
        $sessionId = session()->getId();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $this->testProduct2->idproduct,
            'sku_id' => $this->testProduct2->SkuId,
            'quantity' => 2,
        ]);

        // Verificar carrito de sesión
        $sessionCart = Cart::where('session_id', $sessionId)->where('status', 'active')->first();
        $this->assertNotNull($sessionCart);

        // Paso 3: Usuario se loguea
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->testUser->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.token');

        // Paso 4: Activar merge haciendo request autenticado
        $cartResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/cart');

        $cartResponse->assertStatus(200);

        // Paso 5: Verificar que los carritos se merged correctamente
        $existingUserCart->refresh();
        $this->assertEquals('active', $existingUserCart->status);
        $this->assertEquals(2, $existingUserCart->activeItems()->count()); // Ambos productos

        // Verificar que tiene ambos productos
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $existingUserCart->id,
            'product_id' => $this->testProduct1->idproduct,
            'status' => 'active'
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $existingUserCart->id,
            'product_id' => $this->testProduct2->idproduct,
            'status' => 'active'
        ]);

        // El carrito de sesión debe estar merged
        $sessionCart->refresh();
        $this->assertEquals('merged', $sessionCart->status);

        echo "✅ Carts merge correctly when user already has existing cart\n";
    }

    /** @test */
    public function test_cart_merge_with_same_product_sums_quantities()
    {
        // Paso 1: Usuario ya tiene carrito con producto1
        $existingUserCart = Cart::create([
            'user_id' => $this->testUser->id,
            'status' => 'active',
        ]);

        CartItem::create([
            'cart_id' => $existingUserCart->id,
            'product_id' => $this->testProduct1->idproduct,
            'sku_id' => $this->testProduct1->SkuId,
            'quantity' => 2,
            'unit_price' => $this->testProduct1->UnitPrice,
            'total_price' => $this->testProduct1->UnitPrice * 2,
            'status' => 'active',
        ]);

        // Paso 2: Carrito de sesión con el mismo producto
        $this->postJson('/api/v1/cart/items', [
            'product_id' => $this->testProduct1->idproduct,
            'sku_id' => $this->testProduct1->SkuId,
            'quantity' => 3,
        ]);

        // Paso 3: Login y merge
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->testUser->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.token');

        $cartResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/cart');

        // Paso 4: Verificar que las cantidades se sumaron
        $existingUserCart->refresh();
        $mergedItem = $existingUserCart->activeItems()
                                     ->where('product_id', $this->testProduct1->idproduct)
                                     ->first();

        $this->assertNotNull($mergedItem);
        $this->assertEquals(5, $mergedItem->quantity); // 2 + 3 = 5
        $this->assertEquals($this->testProduct1->UnitPrice * 5, $mergedItem->total_price);

        echo "✅ Same products merge by summing quantities\n";
    }

    /** @test */
    public function test_authenticated_user_can_manage_cart_normally()
    {
        // Login del usuario
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->testUser->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.token');

        // Agregar productos al carrito
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/cart/items', [
            'product_id' => $this->testProduct1->idproduct,
            'sku_id' => $this->testProduct1->SkuId,
            'quantity' => 1,
        ]);

        $response1->assertStatus(201);

        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/cart/items', [
            'product_id' => $this->testProduct2->idproduct,
            'sku_id' => $this->testProduct2->SkuId,
            'quantity' => 2,
        ]);

        $response2->assertStatus(201);

        // Obtener carrito
        $cartResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/cart');

        $cartResponse->assertStatus(200)
                    ->assertJsonStructure([
                        'success',
                        'data' => [
                            'id',
                            'user_id',
                            'status',
                            'items'
                        ],
                        'stats'
                    ]);

        // Verificar carrito del usuario
        $this->assertDatabaseHas('carts', [
            'user_id' => $this->testUser->id,
            'status' => 'active'
        ]);

        echo "✅ Authenticated user can manage cart normally\n";
    }

    /** @test */
    public function test_cart_operations_work_correctly()
    {
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
                               'product_id' => $this->testProduct1->idproduct,
                               'sku_id' => $this->testProduct1->SkuId,
                               'quantity' => 2,
                           ]);

        $addResponse->assertStatus(201);
        $itemId = $addResponse->json('data.id');

        // Actualizar cantidad
        $updateResponse = $this->withHeaders($headers)
                              ->putJson("/api/v1/cart/items/{$itemId}", [
                                  'quantity' => 5,
                              ]);

        $updateResponse->assertStatus(200);

        $this->assertDatabaseHas('cart_items', [
            'id' => $itemId,
            'quantity' => 5,
        ]);

        // Eliminar item
        $deleteResponse = $this->withHeaders($headers)
                              ->deleteJson("/api/v1/cart/items/{$itemId}");

        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('cart_items', [
            'id' => $itemId,
        ]);

        echo "✅ Cart operations (add, update, delete) work correctly\n";
    }

    /** @test */
    public function test_cart_totals_calculate_correctly()
    {
        // Login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->testUser->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.token');
        $headers = ['Authorization' => 'Bearer ' . $token];

        // Agregar productos con precios conocidos
        $this->withHeaders($headers)
             ->postJson('/api/v1/cart/items', [
                 'product_id' => $this->testProduct1->idproduct,
                 'sku_id' => $this->testProduct1->SkuId,
                 'quantity' => 2, // 99.99 * 2 = 199.98
             ]);

        $this->withHeaders($headers)
             ->postJson('/api/v1/cart/items', [
                 'product_id' => $this->testProduct2->idproduct,
                 'sku_id' => $this->testProduct2->SkuId,
                 'quantity' => 1, // 149.99 * 1 = 149.99
             ]);

        // Obtener carrito y verificar totales
        $cartResponse = $this->withHeaders($headers)->getJson('/api/v1/cart');

        $cartResponse->assertStatus(200);

        $stats = $cartResponse->json('stats');
        $expectedSubtotal = (99.99 * 2) + (149.99 * 1); // 349.97

        $this->assertEquals(round($expectedSubtotal, 2), round($stats['subtotal'], 2));
        $this->assertEquals(3, $stats['items_count']); // 2 + 1
        $this->assertEquals(2, $stats['unique_products']);

        echo "✅ Cart totals calculate correctly\n";
    }
}
