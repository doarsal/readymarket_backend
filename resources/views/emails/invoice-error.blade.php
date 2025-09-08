<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error en Facturación - Readymarket</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: #dc3545;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            margin: -20px -20px 20px -20px;
        }
        .alert {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        .order-info {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 20px 0;
        }
        .customer-info {
            background: #e8f5e8;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
        }
        .invoice-data {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
        }
        .error-details {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .items-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #666;
        }
        .highlight {
            background-color: #ffeb3b;
            padding: 2px 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧾🚨 Error en Generación de Factura</h1>
            <p>Sistema de Facturación Electrónica - Readymarket</p>
        </div>

        <div class="alert">
            <strong>⚠️ ATENCIÓN:</strong> No se pudo generar la factura para la orden <strong>{{ $order->order_number }}</strong>
        </div>

        <div class="order-info">
            <h3>📋 Información de la Orden</h3>
            <p><strong>Número de Orden:</strong> {{ $order->order_number }}</p>
            <p><strong>ID de Orden:</strong> {{ $order->id }}</p>
            <p><strong>Total:</strong> ${{ number_format($order->total_amount, 2) }} {{ $order->currency }}</p>
            <p><strong>Estado de Pago:</strong> <span class="highlight">{{ $order->payment_status }}</span></p>
            <p><strong>Fecha de Orden:</strong> {{ $order->created_at->format('d/m/Y H:i:s') }}</p>
        </div>

        <div class="customer-info">
            <h3>👤 Información del Cliente</h3>
            <p><strong>Cliente:</strong> {{ $order->user->name ?? 'N/A' }}</p>
            <p><strong>Email:</strong> {{ $order->user->email ?? 'N/A' }}</p>
            @if($order->user->phone)
                <p><strong>Teléfono:</strong> {{ $order->user->phone }}</p>
            @endif
        </div>

        @if(!empty($receiverData))
        <div class="invoice-data">
            <h3>🧾 Datos para Facturación</h3>
            <p><strong>RFC:</strong> {{ $receiverData['rfc'] ?? 'N/A' }}</p>
            <p><strong>Razón Social:</strong> {{ $receiverData['name'] ?? 'N/A' }}</p>
            <p><strong>Código Postal:</strong> {{ $receiverData['postal_code'] ?? 'N/A' }}</p>
            <p><strong>Régimen Fiscal:</strong> {{ $receiverData['tax_regime'] ?? 'N/A' }}</p>
            <p><strong>Uso de CFDI:</strong> {{ $receiverData['cfdi_use'] ?? 'N/A' }}</p>
        </div>
        @endif

        <div class="error-details">
            <h3>🚨 Detalles del Error</h3>
            <p><strong>Mensaje de Error:</strong></p>
            <pre style="background: #fff; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">{{ $errorMessage }}</pre>

            @if(!empty($errorDetails))
                <p><strong>Detalles Técnicos:</strong></p>
                <pre style="background: #fff; padding: 10px; border-radius: 4px; border: 1px solid #ddd; font-size: 12px;">{{ print_r($errorDetails, true) }}</pre>
            @endif
        </div>

        <h3>🛒 Productos en la Orden</h3>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio Unitario</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product_name ?? $item->product->ProductTitle ?? 'Producto sin nombre' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>${{ number_format($item->unit_price, 2) }}</td>
                    <td>${{ number_format($item->line_total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">
            <p><strong>Timestamp:</strong> {{ $timestamp }}</p>
            <p><strong>Sistema:</strong> Readymarket - Facturación Electrónica</p>
            <p><strong>Servicio:</strong> FacturaloPlus</p>
            <hr>
            <p><em>Este es un mensaje automático del sistema de facturación. Por favor, revise y corrija el problema lo antes posible.</em></p>
        </div>
    </div>
</body>
</html>
