<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\LanguageController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\ExchangeRateController;
use App\Http\Controllers\Api\TranslationController;
use App\Http\Controllers\Api\StoreConfigurationController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\MicrosoftAccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\UserActivityLogController;
use App\Http\Controllers\Api\TaxRegimeController;
use App\Http\Controllers\Api\PostalCodeController;
use App\Http\Controllers\Api\BillingInformationController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\PaymentCardController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\MitecPaymentController;
use App\Http\Controllers\Api\CfdiUsageController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Root API endpoints
// Nota: Las rutas de perfil ahora están en el grupo v1, bajo auth:sanctum

// Microsoft Marketplace API Routes
Route::prefix('v1')->group(function () {

    // Rutas de autenticación
    Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('verify-email', [AuthController::class, 'verifyEmail']);
        Route::post('resend-verification', [AuthController::class, 'resendVerification']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);

        // OTP Verification endpoints
        Route::post('verify-otp', [AuthController::class, 'verifyOTP']);
        Route::post('resend-otp', [AuthController::class, 'resendOTP']);

        // Estos SÍ requieren autenticación
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
        });
    });

    // Public routes (NO requieren autenticación)
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/menu', [CategoryController::class, 'menu']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);
    Route::get('categories/{category}/products', [CategoryController::class, 'products']);
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/popular', [ProductController::class, 'popular']);
    Route::get('products/featured', [ProductController::class, 'featured']);
    Route::get('products/slide', [ProductController::class, 'slide']);
    Route::get('products/search', [ProductController::class, 'search']);
    Route::get('products/by-product-id/{productId}', [ProductController::class, 'showByProductId']);
    Route::get('products/detail/{id}', [ProductController::class, 'showDetail']); // Nueva ruta para ID interno
    Route::get('products/{id}', [ProductController::class, 'show']);

    // Public activities endpoints
    Route::get('activities/active', [ActivityController::class, 'getActive']);
    Route::get('activities', [ActivityController::class, 'index']);
    Route::get('activities/{activity}', [ActivityController::class, 'show']);

    // Public tax regimes endpoints (para consulta pública)
    Route::get('tax-regimes', [TaxRegimeController::class, 'index']);
    Route::get('tax-regimes/grouped', [TaxRegimeController::class, 'getGrouped']);
    Route::get('tax-regimes/{taxRegime}', [TaxRegimeController::class, 'show']);
    Route::get('tax-regimes/{taxRegime}/cfdi-usages', [TaxRegimeController::class, 'getCfdiUsages']);

    // Public postal codes endpoints (para autocompletar direcciones)
    Route::get('postal-codes/search/{code}', [PostalCodeController::class, 'searchByCode']);
    Route::get('postal-codes/autocomplete', [PostalCodeController::class, 'autocomplete']);
    Route::get('postal-codes/address/{code}', [PostalCodeController::class, 'getAddressByCode']);

    // Public CFDI usages endpoints (para consulta pública)
    Route::get('cfdi-usages', [CfdiUsageController::class, 'index']);
    Route::get('cfdi-usages/by-person-type/{personType}', [CfdiUsageController::class, 'getByPersonType']);
    Route::get('cfdi-usages/by-tax-regime/{taxRegimeId}', [CfdiUsageController::class, 'getByTaxRegime']);
    Route::get('cfdi-usages/{cfdiUsage}', [CfdiUsageController::class, 'show']);

    // Health check endpoint (público)
    Route::get('health', [HealthController::class, 'check']);

    // MITEC Payment Gateway endpoints públicos
    Route::get('payments/mitec/config', [MitecPaymentController::class, 'getConfig']);
    Route::post('payments/mitec/webhook', [MitecPaymentController::class, 'webhook']);

    // MITEC Callback - Recibe respuesta de MITEC y procesa
    Route::get('payments/mitec/callback', [MitecPaymentController::class, 'handleCallback']);
    Route::post('payments/mitec/callback', [MitecPaymentController::class, 'handleCallback']);

    // Consultar estado de pago por referencia
    Route::get('payments/status/{reference}', [MitecPaymentController::class, 'getPaymentStatus']);

    // Public order tracking endpoint (no requiere autenticación)
    Route::get('orders/tracking/{order_number}', [OrderController::class, 'trackByOrderNumber']);

    // Public order payment details endpoint (no requiere autenticación)
    Route::get('orders/{order_number}/payment-details', [OrderController::class, 'getPaymentDetails']);

    // Shopping Cart endpoints (PÚBLICOS con autenticación flexible)
    Route::prefix('cart')->middleware('cart')->group(function () {
        Route::get('/', [CartController::class, 'show']);
        Route::post('/items', [CartController::class, 'addItem']);
        Route::put('/items/{item}', [CartController::class, 'updateItem']);
        Route::delete('/items/{item}', [CartController::class, 'removeItem']);
        Route::delete('/clear', [CartController::class, 'clear']);
        Route::post('/mark-abandoned', [CartController::class, 'markAsAbandoned'])->middleware('throttle:10,1');

        // Endpoints que requieren autenticación OBLIGATORIA
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/items/{item}/save-for-later', [CartController::class, 'saveForLater']);
            Route::get('/saved-items', [CartController::class, 'savedItems']);
            Route::post('/items/{item}/move-to-cart', [CartController::class, 'moveToCart']);
        });
    });

    // TODAS las demás rutas requieren autenticación
    Route::middleware('auth:sanctum')->group(function () {

        // Analytics endpoints (TODOS requieren autenticación)
        Route::get('analytics/dashboard', [AnalyticsController::class, 'dashboard']);
        Route::get('analytics/stores', [AnalyticsController::class, 'stores']);
        Route::get('analytics/products/top', [AnalyticsController::class, 'topProducts']);
        Route::get('analytics/categories/performance', [AnalyticsController::class, 'categoriesPerformance']);
        Route::get('analytics/pricing', [AnalyticsController::class, 'pricing']);
        Route::get('analytics/system/health', [AnalyticsController::class, 'systemHealth']);
        Route::get('analytics/page-views', [AnalyticsController::class, 'getPageViews']);
        Route::get('analytics/abandoned-carts', [AnalyticsController::class, 'getAbandonedCarts']);
        Route::get('analytics/abandoned-carts-simple', [AnalyticsController::class, 'getAbandonedCartsSimple']);

        // Tracking endpoints (requieren autenticación)
        Route::post('analytics/track-view', [AnalyticsController::class, 'trackView'])->middleware('throttle:100,1');
        Route::post('analytics/track-cart-abandonment', [AnalyticsController::class, 'trackCartAbandonment'])->middleware('throttle:50,1');
        Route::get('settings', [SettingsController::class, 'index']);
        Route::get('settings/app-info', [SettingsController::class, 'appInfo']);
        Route::get('settings/marketplace', [SettingsController::class, 'marketplaceSettings']);
        Route::put('settings/marketplace', [SettingsController::class, 'updateMarketplaceSettings']);
        Route::get('settings/system-status', [SettingsController::class, 'systemStatus']);

        // Stores endpoints
        Route::apiResource('stores', StoreController::class);
        Route::post('stores/{store}/configurations', [StoreController::class, 'setConfiguration']);
        Route::get('stores/{store}/configurations', [StoreController::class, 'getConfigurations']);

        // Billing Information endpoints
        Route::apiResource('billing-information', BillingInformationController::class);
        Route::delete('billing-information/{id}/force', [BillingInformationController::class, 'forceDelete']);
        Route::post('billing-information/{id}/restore', [BillingInformationController::class, 'restore']);
        Route::post('billing-information/{id}/set-default', [BillingInformationController::class, 'setDefault']);

        // Activities management endpoints (require auth)
        Route::post('activities', [ActivityController::class, 'store']);
        Route::put('activities/{activity}', [ActivityController::class, 'update']);
        Route::delete('activities/{activity}', [ActivityController::class, 'destroy']);
        Route::delete('activities/{id}/force', [ActivityController::class, 'forceDelete']);
        Route::post('activities/{id}/restore', [ActivityController::class, 'restore']);
        Route::post('activities/{activity}/toggle-status', [ActivityController::class, 'toggleStatus']);

        // User Activity Logs endpoints
        Route::post('user-activities/log', [UserActivityLogController::class, 'logActivity']);
        Route::post('user-activities/quick-log/{activityId}', [UserActivityLogController::class, 'quickLog']);
        Route::get('user-activities', [UserActivityLogController::class, 'getUserActivities']);
        Route::get('user-activities/recent', [UserActivityLogController::class, 'getRecentActivities']);
        Route::get('user-activities/stats', [UserActivityLogController::class, 'getActivityStats']);

        // Tax Regimes CRUD endpoints (solo operaciones que requieren autenticación)
        Route::apiResource('tax-regimes', TaxRegimeController::class)->except(['index', 'show']);
        Route::post('tax-regimes/{taxRegime}/toggle-status', [TaxRegimeController::class, 'toggleStatus']);

        // CFDI Usages CRUD endpoints (operaciones que requieren autenticación)
        Route::apiResource('cfdi-usages', CfdiUsageController::class)->except(['index', 'show']);
        Route::post('cfdi-usages/{cfdiUsage}/toggle-status', [CfdiUsageController::class, 'toggleStatus']);
        Route::post('cfdi-usages/{cfdiUsage}/sync-tax-regimes', [CfdiUsageController::class, 'syncTaxRegimes']);

        // Postal Codes CRUD endpoints
        Route::apiResource('postal-codes', PostalCodeController::class);

        // Store languages management
        Route::get('stores/{store}/languages', [StoreController::class, 'getLanguages']);
        Route::post('stores/{store}/languages', [StoreController::class, 'addLanguages']);
        Route::delete('stores/{store}/languages/{languageId}', [StoreController::class, 'removeLanguage']);

        // Store currencies management
    Route::get('stores/{store}/currencies', [StoreController::class, 'getCurrencies']);
    Route::post('stores/{store}/currencies', [StoreController::class, 'addCurrencies']);
    Route::delete('stores/{store}/currencies/{currencyId}', [StoreController::class, 'removeCurrency']);

    // Languages endpoints
    Route::apiResource('languages', LanguageController::class);

    // Currencies endpoints
    Route::apiResource('currencies', CurrencyController::class);

    // Exchange rates endpoints
    Route::get('exchange-rates/convert', [ExchangeRateController::class, 'convert']);
    Route::apiResource('exchange-rates', ExchangeRateController::class);

    // Translations endpoints
    Route::apiResource('translations', TranslationController::class);
    Route::get('translations/by-language/{languageCode}', [TranslationController::class, 'getByLanguage']);
    Route::post('translations/bulk', [TranslationController::class, 'bulkStore']);

    // Store Configurations endpoints
    Route::apiResource('store-configurations', StoreConfigurationController::class);
    Route::get('store-configurations/by-store/{storeId}', [StoreConfigurationController::class, 'getByStore']);
    Route::post('store-configurations/bulk', [StoreConfigurationController::class, 'bulkStore']);

    // Categories endpoints (admin only)
    Route::get('categories/stats', [CategoryController::class, 'getStats']);
    Route::get('categories/by-store/{storeId}', [CategoryController::class, 'getByStore']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::put('categories/{category}', [CategoryController::class, 'update']);
    Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

    // Products endpoints (admin only)
    Route::get('products/stats', [ProductController::class, 'getStats']);
    Route::post('products/clear-cache', [ProductController::class, 'clearCache']);
    Route::get('products/by-store/{storeId}', [ProductController::class, 'getByStore']);
    Route::get('products/by-sku-id/{skuId}', [ProductController::class, 'showBySkuId']);
    Route::post('products', [ProductController::class, 'store']);
    Route::put('products/{id}', [ProductController::class, 'update']);
    Route::delete('products/{id}', [ProductController::class, 'destroy']);

    // Users endpoints
    Route::apiResource('users', UserController::class);
    Route::get('users/{user}/permissions', [UserController::class, 'permissions']);
    Route::post('users/{user}/roles', [UserController::class, 'assignRoles']);
    Route::delete('users/{user}/roles/{roleId}', [UserController::class, 'removeRole']);

    // User Profile endpoints
    Route::get('user/profile-data', [UserController::class, 'getProfileData']);
    Route::post('user/profile', [UserController::class, 'updateProfile']);
    Route::delete('user/profile', [UserController::class, 'deleteProfile']);    // Roles endpoints
    Route::apiResource('roles', RoleController::class);
    Route::get('roles/{role}/permissions', [RoleController::class, 'permissions']);
    Route::post('roles/{role}/permissions', [RoleController::class, 'assignPermissions']);
    Route::delete('roles/{role}/permissions/{permissionId}', [RoleController::class, 'removePermission']);
    Route::get('roles/{role}/users', [RoleController::class, 'users']);

    // Permissions endpoints
    Route::get('permissions/groups', [PermissionController::class, 'groups']);
    Route::apiResource('permissions', PermissionController::class);
    Route::get('permissions/{permission}/roles', [PermissionController::class, 'roles']);

        // Microsoft Accounts endpoints
        Route::prefix('microsoft-accounts')->group(function () {
            Route::get('/', [MicrosoftAccountController::class, 'index']);
            Route::post('/', [MicrosoftAccountController::class, 'store']);
            Route::get('{id}', [MicrosoftAccountController::class, 'show']);
            Route::put('{id}', [MicrosoftAccountController::class, 'update']);
            Route::delete('{id}', [MicrosoftAccountController::class, 'destroy']);
            Route::post('{id}/retry', [MicrosoftAccountController::class, 'retry']);
            Route::post('check-domain', [MicrosoftAccountController::class, 'checkDomain']);
            Route::get('progress', [MicrosoftAccountController::class, 'progress']);
            Route::patch('{id}/set-default', [MicrosoftAccountController::class, 'setDefault']);
            Route::get('{id}/verify', [MicrosoftAccountController::class, 'verify']);
        });

        // Billing Information endpoints
        Route::apiResource('billing-information', BillingInformationController::class);
        Route::post('billing-information/{id}/restore', [BillingInformationController::class, 'restore']);
        Route::delete('billing-information/{id}/force', [BillingInformationController::class, 'forceDelete']);
        Route::post('billing-information/{id}/set-default', [BillingInformationController::class, 'setDefault']);

        // Users management endpoints
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::get('{id}', [UserController::class, 'show']);
            Route::put('{id}', [UserController::class, 'update']);
            Route::delete('{id}', [UserController::class, 'destroy']); // Soft delete
            Route::post('{id}/restore', [UserController::class, 'restore']);
            Route::delete('{id}/force', [UserController::class, 'forceDelete']); // Permanent delete
            Route::post('{id}/deactivate', [UserController::class, 'deactivate']);
            Route::post('{id}/activate', [UserController::class, 'activate']);
        });

        // Payment Cards endpoints (Secure payment card management)
        Route::prefix('payment-cards')->group(function () {
            Route::get('/', [PaymentCardController::class, 'index']);
            Route::post('/', [PaymentCardController::class, 'store']);
            Route::get('validate-expiration', [PaymentCardController::class, 'validateExpiration']);
            Route::get('valid-for-payment', [PaymentCardController::class, 'getValidForPayment']);
            Route::get('{id}', [PaymentCardController::class, 'show']);
            Route::put('{id}', [PaymentCardController::class, 'update']);
            Route::delete('{id}', [PaymentCardController::class, 'destroy']);
            Route::post('{id}/set-default', [PaymentCardController::class, 'setDefault']);
            Route::get('{id}/validate-expiration', [PaymentCardController::class, 'validateCardExpiration']);
        });

        // MITEC Payment Gateway endpoints (Secure payment processing)
        Route::prefix('payments/mitec')->group(function () {
            Route::post('process', [MitecPaymentController::class, 'processPayment']);
            Route::get('sessions/{reference}', [MitecPaymentController::class, 'getPaymentSession']);
            Route::post('process-token', [MitecPaymentController::class, 'processToken']);
            // Nota: status endpoint eliminado - no estaba implementado
        });

        // Orders endpoints (Order management)
        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::post('/', [OrderController::class, 'store']);
            Route::get('statistics', [OrderController::class, 'statistics']);
            Route::get('{id}', [OrderController::class, 'show']);
            Route::put('{id}/cancel', [OrderController::class, 'cancel']);
            Route::post('{id}/process-microsoft', [OrderController::class, 'processMicrosoft']);
            Route::post('{id}/provision', [\App\Http\Controllers\Api\OrderProvisioningController::class, 'processOrder']);
        });

        // Order Provisioning endpoints (Partner Center integration)
        Route::prefix('orders/provisioning')->group(function () {
            Route::post('process/{order_id}', [\App\Http\Controllers\Api\OrderProvisioningController::class, 'processOrder']);
            Route::get('pending', [\App\Http\Controllers\Api\OrderProvisioningController::class, 'getPendingOrders']);
            Route::post('batch-process', [\App\Http\Controllers\Api\OrderProvisioningController::class, 'batchProcessOrders']);
            Route::get('{order_id}/status', [\App\Http\Controllers\Api\OrderProvisioningController::class, 'getOrderStatus']);
        });

    }); // Cierre del middleware auth:sanctum

    // Electronic invoice routes
    Route::prefix('invoices')->name('invoices.')->group(function () {
        // Public routes
        Route::get('test-connection', [\App\Http\Controllers\InvoiceController::class, 'testConnection'])->name('test');

        // Quick invoice generation route (for testing/development)
        Route::post('generate-from-order/{orderId}', [\App\Http\Controllers\InvoiceController::class, 'generateFromOrderId'])->name('generate.from.order');

        // Public download routes (no auth required)
        Route::get('{id}/download/pdf', [\App\Http\Controllers\InvoiceController::class, 'downloadPdf'])->name('download.pdf');
        Route::get('{id}/download/xml', [\App\Http\Controllers\InvoiceController::class, 'downloadXml'])->name('download.xml');

        // Protected routes that require authentication
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('generate', [\App\Http\Controllers\InvoiceController::class, 'generateInvoice'])->name('generate');
            Route::get('/', [\App\Http\Controllers\InvoiceController::class, 'index'])->name('index');
            Route::get('{id}', [\App\Http\Controllers\InvoiceController::class, 'show'])->name('show');
            Route::post('{id}/cancel', [\App\Http\Controllers\InvoiceController::class, 'cancel'])->name('cancel');
            Route::get('{id}/status', [\App\Http\Controllers\InvoiceController::class, 'getStatus'])->name('status');
        });
    });

    // Test routes for purchase confirmations (for development/testing)
    Route::prefix('test')->name('test.')->group(function () {
        Route::post('purchase-confirmations', [\App\Http\Controllers\Api\TestPurchaseConfirmationController::class, 'testConfirmations'])->name('purchase.confirmations');
        Route::get('test-order', [\App\Http\Controllers\Api\TestPurchaseConfirmationController::class, 'getTestOrder'])->name('order');
    });

}); // End v1 route group
