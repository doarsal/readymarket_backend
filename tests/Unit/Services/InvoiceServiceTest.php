<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\User;
use App\Services\InvoiceService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @throws Exception
     */
    #[Test]
    #[DataProvider('correctInvoiceProvider')]
    public function it_creates_an_invoice_correctly(
        string $exchangeRate,
        string $itemPrice,
        string $itemDiscount,
        int $itemQuantity,
        string $totalAmount,
    ): void {
        $exchangeRate = round($exchangeRate, 2);

        $user  = User::factory()->create();
        $store = Store::factory()->create();
        $order = Order::create([
            'user_id'        => $user->getKey(),
            'payment_method' => 'credit_card',
            'payment_status' => 'paid',
            'store_id'       => $store->getKey(),
            'exchange_rate'  => $exchangeRate,
            'total_amount'   => '0.00',
        ]);

        OrderItem::create([
            'order_id'      => $order->getKey(),
            'unit_price'    => $itemPrice,
            'sku_id'        => 'TEST-SKU-001',
            'product_title' => 'Producto de prueba',
            'quantity'      => $itemQuantity,
            'discount'      => $itemDiscount,
            'list_price'    => $itemPrice,
            'line_total'    => $subTotal = round(($itemPrice * $itemQuantity) - $itemDiscount, 2),
        ]);

        $order->update([
            'subtotal'        => $subTotal,
            'tax_amount'      => round($subTotal * 0.16, 2),
            'discount_amount' => $itemDiscount,
            'total_amount'    => $totalAmount,
        ]);

        $service         = new InvoiceService();
        $reflectionClass = new ReflectionClass($service);

        $data = $reflectionClass->getMethod('formatConceptsForCfdiFromOrder')
            ->invoke($service, $order, ['rate' => "0.16", 'tax_code' => 'test', 'factor_type' => 'factor']);

        $totalItems    = 0;
        $totalTaxItems = 0;
        foreach ($data as $item) {
            $itemData      = $item['Impuestos']['Traslados'][0];
            $totalItems    += $itemData['Base'];
            $totalTaxItems += $itemData['Importe'];
        }

        $expectedTotal = round($totalAmount * $exchangeRate, 2);
        $totalInvoice  = round($totalItems + $totalTaxItems, 2);

        $this->assertSame($expectedTotal, $totalInvoice);
    }

    public static function correctInvoiceProvider(): array
    {
        // [exchangeRate, itemPrice, itemDiscount, itemQuantity, totalAmount]
        return [
            // === CASOS BÁSICOS ===
            'basic_no_exchange_no_discount'    => [
                '1.00',
                '100.00',
                '0.00',
                1,
                '116.00',
            ],
            'basic_with_exchange_no_discount'  => [
                '19.20',
                '100.00',
                '0.00',
                1,
                '116.00',
            ],
            'basic_with_exchange_and_discount' => [
                '19.67',
                '97.63',
                '10.00',
                4,
                '100.89',
            ],

            // === CASOS CON DIFERENTES EXCHANGE RATES ===
            'low_exchange_rate'                => [
                '1.05',
                '50.00',
                '0.00',
                2,
                '116.00',
            ],
            'medium_exchange_rate'             => [
                '18.50',
                '100.00',
                '5.00',
                1,
                '110.20',
            ],
            'high_exchange_rate'               => [
                '25.75',
                '200.00',
                '0.00',
                1,
                '232.00',
            ],
            'exchange_rate_with_many_decimals' => [
                '19.876543',
                '100.00',
                '0.00',
                1,
                '116.00',
            ],

            // === CASOS CON MÚLTIPLES CANTIDADES ===
            'multiple_quantity_low'            => [
                '20.00',
                '10.00',
                '0.00',
                5,
                '58.00',
            ],
            'multiple_quantity_high'           => [
                '19.50',
                '25.50',
                '0.00',
                10,
                '295.80',
            ],
            'multiple_quantity_with_discount'  => [
                '18.75',
                '15.00',
                '5.00',
                8,
                '127.60',
            ],

            // === CASOS CON DESCUENTOS VARIADOS ===
            'small_discount'                   => [
                '19.20',
                '100.00',
                '1.50',
                1,
                '114.26',
            ],
            'large_discount'                   => [
                '20.00',
                '100.00',
                '50.00',
                2,
                '116.00',
            ],
            'discount_almost_full_price'       => [
                '19.00',
                '100.00',
                '99.00',
                1,
                '1.16',
            ],

            // === CASOS CON PRECIOS DECIMALES COMPLEJOS ===
            'price_with_many_decimals'         => [
                '19.67',
                '97.63',
                '0.00',
                4,
                '453.40',
            ],
            'price_ending_in_99'               => [
                '19.50',
                '99.99',
                '0.00',
                1,
                '115.99',
            ],
            'price_ending_in_01'               => [
                '20.00',
                '50.01',
                '0.00',
                2,
                '116.02',
            ],
            'very_small_price'                 => [
                '19.00',
                '0.50',
                '0.00',
                10,
                '5.80',
            ],
            'price_with_33_cents'              => [
                '19.20',
                '33.33',
                '0.00',
                3,
                '116.03',
            ],

            // === CASOS CRÍTICOS DE REDONDEO ===
            'rounding_edge_case_1'             => [
                '19.67',
                '10.10',
                '0.00',
                10,
                '117.16',
            ],
            'rounding_edge_case_2'             => [
                '18.95',
                '7.77',
                '0.00',
                15,
                '135.26',
            ],
            'rounding_edge_case_3'             => [
                '20.15',
                '12.34',
                '1.23',
                8,
                '114.95',
            ],

            // === CASOS CON TOTALES ESPECÍFICOS ===
            'total_exactly_100'                => [
                '1.00',
                '86.21',
                '0.00',
                1,
                '100.00',
            ],
            'total_exactly_1000'               => [
                '20.00',
                '862.07',
                '0.00',
                1,
                '1000.00',
            ],
            'total_very_small'                 => [
                '19.00',
                '0.86',
                '0.00',
                1,
                '1.00',
            ],
            'total_very_large'                 => [
                '19.50',
                '8620.69',
                '0.00',
                1,
                '10000.00',
            ],

            // === CASOS COMBINADOS COMPLEJOS ===
            'complex_case_1'                   => [
                '19.876',
                '123.45',
                '12.34',
                7,
                '1000.00',
            ],
            'complex_case_2'                   => [
                '18.234',
                '99.99',
                '5.55',
                3,
                '327.89',
            ],
            'complex_case_3'                   => [
                '21.567',
                '45.67',
                '0.89',
                12,
                '632.10',
            ],

            // === CASOS EXTREMOS ===
            'minimum_values'                   => [
                '1.00',
                '0.01',
                '0.00',
                1,
                '0.01',
            ],
            'exchange_rate_exactly_1'          => [
                '1.00',
                '50.00',
                '0.00',
                2,
                '116.00',
            ],
            'quantity_1_high_price'            => [
                '19.50',
                '5000.00',
                '0.00',
                1,
                '5800.00',
            ],

            // === CASOS REALES REPORTADOS ===
            'original_test_case_1'             => [
                '19.20',
                '100.00',
                '0.00',
                1,
                '116.00',
            ],
            'original_test_case_2'             => [
                '19.67',
                '97.63',
                '0.00',
                4,
                '100.89',
            ],
            'original_test_case_3'             => [
                '19.67',
                '97.63',
                '10.00',
                4,
                '100.89',
            ],
        ];
    }

    #[Test]
    public function it_creates_invoice_with_multiple_items_correctly(): void
    {
        $user  = User::factory()->create();
        $store = Store::factory()->create();
        $order = Order::create([
            'user_id'        => $user->getKey(),
            'payment_method' => 'credit_card',
            'payment_status' => 'paid',
            'store_id'       => $store->getKey(),
            'exchange_rate'  => '19.50',
            'total_amount'   => '0.00',
        ]);

        // Item 1
        OrderItem::create([
            'order_id'      => $order->getKey(),
            'unit_price'    => '100.00',
            'sku_id'        => 'TEST-SKU-001',
            'product_title' => 'Producto 1',
            'quantity'      => 2,
            'discount'      => '10.00',
            'list_price'    => '100.00',
            'line_total'    => 190.00, // (100 * 2) - 10
        ]);

        // Item 2
        OrderItem::create([
            'order_id'      => $order->getKey(),
            'unit_price'    => '50.00',
            'sku_id'        => 'TEST-SKU-002',
            'product_title' => 'Producto 2',
            'quantity'      => 3,
            'discount'      => '5.00',
            'list_price'    => '50.00',
            'line_total'    => 145.00, // (50 * 3) - 5
        ]);

        // Item 3
        OrderItem::create([
            'order_id'      => $order->getKey(),
            'unit_price'    => '25.50',
            'sku_id'        => 'TEST-SKU-003',
            'product_title' => 'Producto 3',
            'quantity'      => 1,
            'discount'      => '0.00',
            'list_price'    => '25.50',
            'line_total'    => 25.50,
        ]);

        $subtotal = 190.00 + 145.00 + 25.50; // 360.50
        $order->update([
            'subtotal'        => $subtotal,
            'tax_amount'      => round($subtotal * 0.16, 2), // 57.68
            'discount_amount' => 15.00,
            'total_amount'    => round($subtotal * 1.16, 2), // 418.18
        ]);

        $service         = new InvoiceService();
        $reflectionClass = new ReflectionClass($service);

        $data = $reflectionClass->getMethod('formatConceptsForCfdiFromOrder')
            ->invoke($service, $order, ['rate' => 0.16, 'tax_code' => 'test', 'factor_type' => 'factor']);

        $totalItems    = 0;
        $totalTaxItems = 0;
        foreach ($data as $item) {
            $itemData      = $item['Impuestos']['Traslados'][0];
            $totalItems    += $itemData['Base'];
            $totalTaxItems += $itemData['Importe'];
        }

        $expectedTotal = round(418.18 * 19.50, 2); // 8154.51

        $this->assertSame($expectedTotal, $totalItems + $totalTaxItems);
        $this->assertCount(3, $data); // Verificar que hay 3 conceptos
    }
}
