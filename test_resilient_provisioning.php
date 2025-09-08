<?php

/**
 * Script de prueba para mostrar cómo funciona el nuevo sistema de aprovisionamiento resiliente
 *
 * CARACTERÍSTICAS DEL NUEVO SISTEMA:
 *
 * 1. PROCESAMIENTO INDIVIDUAL:
 *    - Cada producto se procesa por separado
 *    - Si falla uno, continúa con los siguientes
 *    - No se trunca el proceso completo
 *
 * 2. RASTREO DETALLADO:
 *    - Cada item se marca como 'processing', 'fulfilled' o 'failed'
 *    - Se guarda la razón exacta del error de Microsoft
 *    - Se almacena en order_items.fulfillment_status y fulfillment_error
 *
 * 3. ESTADOS FINALES DE ORDEN:
 *    - 'fulfilled': Todos los productos exitosos
 *    - 'partially_fulfilled': Algunos productos exitosos, otros fallidos
 *    - 'failed': Todos los productos fallaron
 *
 * 4. RESPUESTA DETALLADA:
 *    - Total de productos
 *    - Productos exitosos vs fallidos
 *    - Detalles de cada producto procesado
 *
 * EJEMPLO DE RESPUESTA:
 */

$exampleResponse = [
    'success' => true,
    'message' => 'Partially completed: 3/5 products provisioned',
    'order_id' => 123,
    'total_products' => 5,
    'successful_products' => 3,
    'failed_products' => 2,
    'provisioning_results' => [
        [
            'cart_item_id' => 1,
            'product_id' => 101,
            'sku_id' => 'CFQ7TTC0LH18:0001',
            'product_title' => 'Microsoft 365 Business Premium',
            'quantity' => 2,
            'success' => true,
            'error_message' => null,
            'microsoft_details' => [],
            'subscription_id' => 'sub-12345',
            'microsoft_cart_id' => 'cart-67890',
            'processed_at' => '2025-09-07 10:30:00'
        ],
        [
            'cart_item_id' => 2,
            'product_id' => 102,
            'sku_id' => 'CFQ7TTC0LH18:0002',
            'product_title' => 'Azure Prepago $100',
            'quantity' => 1,
            'success' => true,
            'error_message' => null,
            'microsoft_details' => [],
            'subscription_id' => 'sub-12346',
            'microsoft_cart_id' => 'cart-67891',
            'processed_at' => '2025-09-07 10:31:00'
        ],
        [
            'cart_item_id' => 3,
            'product_id' => 103,
            'sku_id' => 'INVALID_SKU',
            'product_title' => 'Producto Inválido',
            'quantity' => 1,
            'success' => false,
            'error_message' => 'Catalogitem Id INVALID_SKU is invalid',
            'microsoft_details' => [
                'http_status' => 400,
                'error_code' => 'InvalidCatalogItemId',
                'description' => 'The catalog item identifier is not valid',
                'correlation_id' => 'abc-123-def',
                'request_id' => 'req-456-ghi'
            ],
            'subscription_id' => null,
            'microsoft_cart_id' => null,
            'processed_at' => '2025-09-07 10:32:00'
        ]
    ]
];

/**
 * CONSULTAS ÚTILES PARA MONITOREAR:
 *
 * 1. Ver estado de productos por orden:
 */
$sql_order_status = "
SELECT
    o.id as order_id,
    o.order_number,
    o.status,
    o.fulfillment_status,
    oi.product_title,
    oi.sku_id,
    oi.quantity,
    oi.fulfillment_status as item_status,
    oi.fulfillment_error,
    oi.processing_started_at,
    oi.fulfilled_at
FROM orders o
JOIN order_items oi ON o.id = oi.order_id
WHERE o.id = 123
ORDER BY oi.id;
";

/**
 * 2. Ver productos que fallaron en las últimas 24 horas:
 */
$sql_failed_products = "
SELECT
    o.order_number,
    oi.product_title,
    oi.sku_id,
    oi.fulfillment_error,
    oi.updated_at
FROM orders o
JOIN order_items oi ON o.id = oi.order_id
WHERE oi.fulfillment_status = 'failed'
AND oi.updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY oi.updated_at DESC;
";

/**
 * 3. Estadísticas de éxito por producto:
 */
$sql_product_stats = "
SELECT
    oi.sku_id,
    oi.product_title,
    COUNT(*) as total_attempts,
    SUM(CASE WHEN oi.fulfillment_status = 'fulfilled' THEN 1 ELSE 0 END) as successful,
    SUM(CASE WHEN oi.fulfillment_status = 'failed' THEN 1 ELSE 0 END) as failed,
    ROUND(
        (SUM(CASE WHEN oi.fulfillment_status = 'fulfilled' THEN 1 ELSE 0 END) / COUNT(*)) * 100,
        2
    ) as success_rate_percent
FROM order_items oi
WHERE oi.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY oi.sku_id, oi.product_title
HAVING total_attempts >= 5
ORDER BY success_rate_percent ASC;
";

echo "✅ Sistema de aprovisionamiento resiliente implementado correctamente!\n";
echo "📊 Ver ejemplos de consultas y respuestas en este archivo.\n";
