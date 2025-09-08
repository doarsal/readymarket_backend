<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Confirmación de Compra - {{ $order->order_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #2563eb;
            color: white;
            text-align: center;
            padding: 20px;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f8fafc;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .order-details {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #10b981;
        }
        .payment-details {
            background-color: #e0f2fe;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #0288d1;
        }
        .success-badge {
            background-color: #dcfce7;
            color: #166534;
            padding: 8px 16px;
            border-radius: 20px;
            display: inline-block;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .amount {
            font-size: 24px;
            font-weight: bold;
            color: #059669;
            text-align: center;
            margin: 20px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 4px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .detail-label {
            font-weight: bold;
            color: #374151;
        }
        .detail-value {
            color: #6b7280;
        }
        .footer {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin-top: 30px;
        }
        .product-item {
            background: white;
            margin: 10px 0;
            padding: 15px;
            border-left: 4px solid #0078d4;
            border-radius: 4px;
        }
        .microsoft-info {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>¡Compra Confirmada!</h1>
        <p>Gracias por tu compra en Readymind México</p>
    </div>

    <div class="content">
        <div class="success-badge">✅ Pago Procesado Exitosamente</div>

        <p>Estimado(a) {{ $customer->name ?? 'cliente' }},</p>

        <p>Tu compra ha sido procesada exitosamente. A continuación encontrarás los detalles completos de tu pedido y transacción:</p>

        @if(isset($paymentData) && $paymentData)
        <div class="payment-details">
            <h3>💳 Información de la Transacción</h3>
            <div class="detail-row">
                <span class="detail-label">Referencia de Pago:</span>
                <span class="detail-value">{{ $paymentData['reference'] ?? 'N/A' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Código de Autorización:</span>
                <span class="detail-value">{{ $paymentData['auth_code'] ?? 'N/A' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Monto Procesado:</span>
                <span class="detail-value">${{ number_format($paymentData['amount'] ?? 0, 2) }} {{ $paymentData['currency'] ?? '' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Fecha de Procesamiento:</span>
                <span class="detail-value">{{ isset($paymentData['processed_at']) ? \Carbon\Carbon::parse($paymentData['processed_at'])->format('d/m/Y H:i:s') : 'N/A' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Método de Pago:</span>
                <span class="detail-value">{{ $order->payment_method ?? 'Tarjeta de Crédito/Débito' }}</span>
            </div>
            @if($order->paymentResponse && $order->paymentResponse->card_last_four)
            <div class="detail-row">
                <span class="detail-label">Tarjeta Utilizada:</span>
                <span class="detail-value">{{ $order->paymentResponse->getCardInfo()['display_text'] }}</span>
            </div>
            @endif
        </div>
        @endif

        <div class="order-details">
            <h3>📋 Detalles del Pedido</h3>
            <div class="detail-row">
                <span class="detail-label">Número de Pedido:</span>
                <span class="detail-value">{{ $order->order_number }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Estado del Pedido:</span>
                <span class="detail-value">{{ ucfirst($order->status) }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Estado del Pago:</span>
                <span class="detail-value">{{ ucfirst($order->payment_status ?? 'completed') }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Fecha del Pedido:</span>
                <span class="detail-value">{{ $order->created_at->format('d/m/Y H:i:s') }}</span>
            </div>

            <div class="amount">
                Total: ${{ number_format($order->total_amount, 2) }} {{ $order->currency->code ?? 'MXN' }}
            </div>

            @if($order->subtotal || $order->tax_amount || $order->discount_amount)
            <h4>💰 Desglose del Total</h4>
            @if($order->subtotal)
            <div class="detail-row">
                <span class="detail-label">Subtotal:</span>
                <span class="detail-value">${{ number_format($order->subtotal, 2) }}</span>
            </div>
            @endif
            @if($order->tax_amount)
            <div class="detail-row">
                <span class="detail-label">Impuestos:</span>
                <span class="detail-value">${{ number_format($order->tax_amount, 2) }}</span>
            </div>
            @endif
            @if($order->discount_amount)
            <div class="detail-row">
                <span class="detail-label">Descuento:</span>
                <span class="detail-value">-${{ number_format($order->discount_amount, 2) }}</span>
            </div>
            @endif
            @endif
        </div>

        <h4>🛒 Productos Adquiridos</h4>
        @if(isset($items) && $items)
        @foreach($items as $item)
        <div class="product-item">
            <h5>{{ $item->product_title ?? $item->product->ProductTitle ?? 'Producto sin nombre' }}</h5>
            <p><strong>Cantidad:</strong> {{ $item->quantity }}</p>
            <p><strong>Precio unitario:</strong> ${{ number_format($item->unit_price, 2) }}</p>
            <p><strong>Total:</strong> ${{ number_format($item->line_total, 2) }}</p>
        </div>
        @endforeach
        @endif

        @if(isset($microsoft_account) && $microsoft_account)
        <div class="microsoft-info">
            <h4>🔑 Tu Cuenta Microsoft</h4>
            <p>Se ha creado tu cuenta Microsoft con los siguientes datos:</p>
            <p><strong>Dominio:</strong> {{ $microsoft_account->domain_concatenated ?? $microsoft_account->domain }}</p>
            <p><strong>Usuario Administrador:</strong> admin@{{ $microsoft_account->domain_concatenated ?? $microsoft_account->domain }}</p>
            <p><strong>Portal de Administración:</strong> <a href="https://admin.microsoft.com">https://admin.microsoft.com</a></p>
            <p><em>Las credenciales de acceso se enviarán en un correo por separado.</em></p>
        </div>
        @elseif($order->microsoftAccount)
        <div class="microsoft-info">
            <h4>🔑 Cuenta de Microsoft</h4>
            <div class="detail-row">
                <span class="detail-label">Dominio:</span>
                <span class="detail-value">{{ $order->microsoftAccount->domain }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Nombre:</span>
                <span class="detail-value">{{ $order->microsoftAccount->first_name }} {{ $order->microsoftAccount->last_name }}</span>
            </div>
        </div>
        @endif

        @if(isset($billing_info) && $billing_info)
        <div class="order-details">
            <h3>🧾 Información de Facturación</h3>
            <div class="detail-row">
                <span class="detail-label">RFC:</span>
                <span class="detail-value">{{ $billing_info->rfc }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Organización:</span>
                <span class="detail-value">{{ $billing_info->company_name ?? $billing_info->organization }}</span>
            </div>
            @if($billing_info->postal_code)
            <div class="detail-row">
                <span class="detail-label">Código Postal:</span>
                <span class="detail-value">{{ $billing_info->postal_code }}</span>
            </div>
            @endif
            @if($billing_info->email)
            <div class="detail-row">
                <span class="detail-label">Email de Facturación:</span>
                <span class="detail-value">{{ $billing_info->email }}</span>
            </div>
            @endif
            @if($billing_info->phone)
            <div class="detail-row">
                <span class="detail-label">Teléfono:</span>
                <span class="detail-value">{{ $billing_info->phone }}</span>
            </div>
            @endif
            @if($billing_info->taxRegime)
            <div class="detail-row">
                <span class="detail-label">Régimen Fiscal:</span>
                <span class="detail-value">{{ $billing_info->taxRegime->name }}</span>
            </div>
            @endif
        </div>
        @elseif($order->billing_information)
        <div class="order-details">
            <h3>🧾 Información de Facturación</h3>
            <div class="detail-row">
                <span class="detail-label">Organización:</span>
                <span class="detail-value">{{ $order->billing_information->organization }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">RFC:</span>
                <span class="detail-value">{{ $order->billing_information->rfc }}</span>
            </div>
            @if($order->billing_information->postal_code)
            <div class="detail-row">
                <span class="detail-label">Código Postal:</span>
                <span class="detail-value">{{ $order->billing_information->postal_code }}</span>
            </div>
            @endif
            @if($order->billing_information->email)
            <div class="detail-row">
                <span class="detail-label">Email de Facturación:</span>
                <span class="detail-value">{{ $order->billing_information->email }}</span>
            </div>
            @endif
            @if($order->billing_information->phone)
            <div class="detail-row">
                <span class="detail-label">Teléfono:</span>
                <span class="detail-value">{{ $order->billing_information->phone }}</span>
            </div>
            @endif
        </div>
        @endif

        <div style="background: #d4edda; padding: 15px; margin: 15px 0; border-radius: 5px; border: 1px solid #c3e6cb;">
            <h4>📧 Próximos Pasos</h4>
            <ul>
                <li>Tu pedido será aprovisionado en Microsoft Partner Center automáticamente</li>
                <li>Recibirás un email adicional con las credenciales de acceso a tus productos</li>
                <li>Si hay algún problema, nuestro equipo procesará tu pedido manualmente</li>
                <li>Podrás acceder a tus productos en el portal de Microsoft</li>
                <li>Si tienes preguntas, no dudes en contactarnos citando el número de pedido <strong>{{ $order->order_number }}</strong></li>
            </ul>
        </div>

        <p>¡Gracias por confiar en nosotros!</p>

        <p><strong>Equipo de Readymind México</strong></p>
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} ReadyMarket - Microsoft Partner</p>
        <p>Este es un email automático, por favor no respondas a este mensaje.</p>
        <p>Para soporte, contacta a: <a href="mailto:infowebs@readymind.ms">infowebs@readymind.ms</a></p>
    </div>
</body>
</html>
