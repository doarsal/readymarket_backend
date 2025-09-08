<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>No se procesó pedido en Readymarket - Detalles por Producto</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .header { background-color: #d32f2f; color: white; padding: 15px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .error-box { background-color: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin: 15px 0; }
        .order-info { background-color: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 15px 0; }
        .customer-info { background-color: #f3e5f5; border-left: 4px solid #9c27b0; padding: 15px; margin: 15px 0; }
        .products-info { background-color: #e8f5e8; border-left: 4px solid #4caf50; padding: 15px; margin: 15px 0; }
        .product-failed { background-color: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin: 10px 0; }
        .product-success { background-color: #e8f5e8; border-left: 4px solid #4caf50; padding: 15px; margin: 10px 0; }
        .microsoft-error { background-color: #fff3e0; border-left: 4px solid #ff9800; padding: 15px; margin: 15px 0; }
        .summary-box { background-color: #f5f5f5; border: 2px solid #ccc; padding: 15px; margin: 15px 0; }
        .footer { text-align: center; padding: 10px; font-size: 12px; color: #666; }
        .timestamp { font-style: italic; color: #666; }
        .code-block { background-color: #f5f5f5; border: 1px solid #ddd; padding: 10px; font-family: monospace; font-size: 12px; }
        .status-success { color: #4caf50; font-weight: bold; }
        .status-failed { color: #f44336; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 No se procesó pedido en Readymarket</h1>
            <p>Reporte detallado por producto</p>
        </div>

        <div class="content">
            <p><strong>Se ha detectado un error al procesar una orden del Readymarket en Microsoft Partner Center.</strong></p>

            <div class="order-info">
                <h3>📋 Información de la Orden</h3>
                <p><strong>ID:</strong> {{ $order->id }}</p>
                <p><strong>Número de Orden:</strong> {{ $order->order_number }}</p>
                <p><strong>Estado:</strong> {{ $order->status }}</p>
                <p><strong>Total:</strong> ${{ number_format($order->total_amount, 2) }}</p>
                <p><strong>Fecha de Creación:</strong> {{ $order->created_at->format('d/m/Y H:i:s') }}</p>
            </div>

            <div class="customer-info">
                <h3>👤 Información del Cliente</h3>
                @if($order->user)
                    <p><strong>Nombre:</strong> {{ $order->user->name ?? 'No disponible' }}</p>
                    <p><strong>Email:</strong> {{ $order->user->email }}</p>
                    <p><strong>Teléfono:</strong> {{ $order->user->phone ?? 'No disponible' }}</p>
                @endif
                @if($order->microsoftAccount)
                    <p><strong>Microsoft Customer ID:</strong> {{ $order->microsoftAccount->microsoft_id }}</p>
                    <p><strong>Dominio:</strong> {{ $order->microsoftAccount->domain_concatenated }}</p>
                @endif
            </div>

            <div class="summary-box">
                <h3>📊 Resumen del Procesamiento</h3>
                @if(isset($errorDetails['Total Products']))
                    <p><strong>Total de Productos:</strong> {{ $errorDetails['Total Products'] }}</p>
                    <p><strong class="status-success">✅ Productos Exitosos:</strong> {{ $errorDetails['Successful Products'] }}</p>
                    <p><strong class="status-failed">❌ Productos Fallidos:</strong> {{ $errorDetails['Failed Products'] }}</p>
                @endif
                <p><strong>Mensaje:</strong> {{ $errorMessage }}</p>
            </div>

            <div class="products-info">
                <h3>📦 Detalles por Producto</h3>

                @if(isset($productResults) && count($productResults) > 0)
                    @foreach($productResults as $index => $product)
                        <div class="{{ $product['success'] ? 'product-success' : 'product-failed' }}">
                            <h4>Producto {{ $index + 1 }}: {{ $product['product_title'] }}</h4>
                            <p><strong>ID del Producto:</strong> {{ $product['product_id'] }}</p>
                            <p><strong>Cantidad:</strong> {{ $product['quantity'] }}</p>
                            <p><strong>Estado:</strong>
                                @if($product['success'])
                                    <span class="status-success">✅ EXITOSO</span>
                                @else
                                    <span class="status-failed">❌ FALLIDO</span>
                                @endif
                            </p>
                            <p><strong>Procesado en:</strong> {{ $product['processed_at'] ?? 'N/A' }}</p>

                            @if($product['success'])
                                @if(isset($product['subscription_id']))
                                    <p><strong>Subscription ID:</strong> {{ $product['subscription_id'] }}</p>
                                @endif
                                @if(isset($product['microsoft_cart_id']))
                                    <p><strong>Microsoft Cart ID:</strong> {{ $product['microsoft_cart_id'] }}</p>
                                @endif
                            @else
                                <div class="microsoft-error">
                                    <h5>❌ Error Específico</h5>
                                    <p><strong>Mensaje:</strong> {{ $product['error_message'] ?? 'Error desconocido' }}</p>

                                    @if(isset($product['microsoft_details']) && !empty($product['microsoft_details']))
                                        <h6>🔗 Detalles de Microsoft</h6>
                                        @php $details = $product['microsoft_details']; @endphp

                                        @if(isset($details['http_status']))
                                            <p><strong>Código HTTP:</strong> {{ $details['http_status'] }}</p>
                                        @endif

                                        @if(isset($details['error_code']))
                                            <p><strong>Código de Error:</strong> {{ $details['error_code'] }}</p>
                                        @endif

                                        @if(isset($details['description']))
                                            <p><strong>Descripción:</strong> {{ $details['description'] }}</p>
                                        @endif

                                        @if(isset($details['correlation_id']))
                                            <p><strong>MS-CorrelationId:</strong> {{ $details['correlation_id'] }}</p>
                                        @endif

                                        @if(isset($details['request_id']))
                                            <p><strong>MS-RequestId:</strong> {{ $details['request_id'] }}</p>
                                        @endif

                                        @if(isset($details['raw_response']))
                                            <p><strong>Respuesta Completa:</strong></p>
                                            <div class="code-block">{{ $details['raw_response'] }}</div>
                                        @endif
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                @else
                    <p><em>No se encontraron detalles de productos.</em></p>
                @endif
            </div>

            <div class="error-box">
                <h3>🔧 Acción Requerida</h3>
                <p><strong>Pasos a seguir:</strong></p>
                <ul>
                    <li>Revisar los errores específicos de cada producto</li>
                    <li>Verificar la configuración de productos en Microsoft Partner Center</li>
                    <li>Usar los Correlation IDs para contactar soporte de Microsoft si es necesario</li>
                    <li>Considerar reintento manual para productos específicos fallidos</li>
                    <li>Revisar los parámetros de BillingCycle y TermDuration para productos problemáticos</li>
                </ul>
            </div>

            <div class="timestamp">
                <p><strong>Fecha y Hora:</strong> {{ $timestamp }}</p>
            </div>
        </div>

        <div class="footer">
            <p>Este mensaje fue generado automáticamente por el Readymarket.</p>
            <p>Para más información técnica, revisar los logs del sistema.</p>
        </div>
    </div>
</body>
</html>
