<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>No se procesó pedido en Readymarket</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #d32f2f; color: white; padding: 15px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .error-box { background-color: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin: 15px 0; }
        .order-info { background-color: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 15px 0; }
        .customer-info { background-color: #f3e5f5; border-left: 4px solid #9c27b0; padding: 15px; margin: 15px 0; }
        .products-info { background-color: #e8f5e8; border-left: 4px solid #4caf50; padding: 15px; margin: 15px 0; }
        .microsoft-error { background-color: #fff3e0; border-left: 4px solid #ff9800; padding: 15px; margin: 15px 0; }
        .footer { text-align: center; padding: 10px; font-size: 12px; color: #666; }
        .timestamp { font-style: italic; color: #666; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .code-block { background-color: #f5f5f5; border: 1px solid #ddd; padding: 10px; font-family: monospace; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 No se procesó pedido en Readymarket</h1>
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

            <div class="products-info">
                <h3>🛒 Productos en la Orden</h3>
                @if($order->cartItems && $order->cartItems->count() > 0)
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>SKU</th>
                                <th>Cantidad</th>
                                <th>Precio Unit.</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->cartItems as $item)
                                <tr>
                                    <td>{{ $item->product->ProductTitle ?? $item->product->SkuTitle ?? 'Producto no disponible' }}</td>
                                    <td>{{ $item->product->SkuId ?? 'N/A' }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>${{ number_format($item->unit_price, 2) }}</td>
                                    <td>${{ number_format($item->total_price, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p><em>No se encontraron productos en esta orden.</em></p>
                @endif
            </div>

            @if(isset($microsoftErrorDetails))
            <div class="microsoft-error">
                <h3>🔗 Respuesta Exacta de Microsoft Partner Center</h3>
                <p><strong>Código de Estado HTTP:</strong> {{ $microsoftErrorDetails['http_status'] ?? 'N/A' }}</p>
                <p><strong>Código de Error:</strong> {{ $microsoftErrorDetails['error_code'] ?? 'N/A' }}</p>
                <p><strong>Descripción:</strong> {{ $microsoftErrorDetails['description'] ?? 'N/A' }}</p>

                @if(isset($microsoftErrorDetails['raw_response']))
                    <p><strong>Respuesta Completa:</strong></p>
                    <div class="code-block">{{ $microsoftErrorDetails['raw_response'] }}</div>
                @endif

                @if(isset($microsoftErrorDetails['correlation_id']))
                    <p><strong>MS-CorrelationId:</strong> {{ $microsoftErrorDetails['correlation_id'] }}</p>
                @endif

                @if(isset($microsoftErrorDetails['request_id']))
                    <p><strong>MS-RequestId:</strong> {{ $microsoftErrorDetails['request_id'] }}</p>
                @endif
            </div>
            @endif

            <div class="error-box">
                <h3>❌ Resumen del Error</h3>
                <p><strong>Mensaje:</strong> {{ $errorMessage }}</p>

                @if(!empty($errorDetails))
                    <h4>Información Adicional:</h4>
                    <ul>
                        @foreach($errorDetails as $key => $value)
                            <li><strong>{{ $key }}:</strong> {{ is_array($value) ? json_encode($value) : $value }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="timestamp">
                <p><strong>Fecha y Hora:</strong> {{ $timestamp }}</p>
            </div>

            <p><strong>Acción Requerida:</strong></p>
            <ul>
                <li>Revisar la configuración de Microsoft Partner Center</li>
                <li>Verificar el estado del programa de Reseller</li>
                <li>Contactar al soporte de Microsoft si es necesario</li>
                <li>Procesar la orden manualmente si es urgente</li>
            </ul>
        </div>

        <div class="footer">
            <p>Este mensaje fue generado automáticamente por el Readymarket.</p>
        </div>
    </div>
</body>
</html>
