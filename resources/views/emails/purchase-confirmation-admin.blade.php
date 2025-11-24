<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Nueva Compra - {{ $order->order_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
        }
        .header {
            background: #dc3545;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            padding: 20px;
            background: #f9f9f9;
        }
        .footer {
            background: #333;
            color: white;
            padding: 15px;
            text-align: center;
            border-radius: 0 0 8px 8px;
        }
        .product-item {
            background: white;
            margin: 10px 0;
            padding: 15px;
            border-left: 4px solid #dc3545;
            border-radius: 4px;
        }
        .total {
            font-size: 18px;
            font-weight: bold;
            color: #dc3545;
        }
        .info-box {
            background: white;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .payment-box {
            background: #e0f2fe;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-left: 4px solid #0288d1;
        }
        .microsoft-info {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .action-required {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ffeaa7;
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
        .alert {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🆕 NUEVA COMPRA REALIZADA</h1>
            <h2>Notificación Administrativa</h2>
            <p>Pedido: {{ $order->order_number }}</p>
        </div>

        <div class="content">
            @if(isset($paymentData) && $paymentData)
            <div class="payment-box">
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
                    <span class="detail-value">${{ number_format($paymentData['amount'] ?? 0, 2) }} {{ $paymentData['currency'] ?? 'MXN' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Fecha de Procesamiento:</span>
                    <span class="detail-value">{{ isset($paymentData['processed_at']) ? \Carbon\Carbon::parse($paymentData['processed_at'])->format('d/m/Y H:i:s') : 'N/A' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Estado de Transacción:</span>
                    <span class="detail-value" style="color: #059669; font-weight: bold;">✅ EXITOSA</span>
                </div>
            </div>
            @endif

            <div class="info-box">
                <h3>📋 Información del Pedido</h3>
                <div class="detail-row">
                    <span class="detail-label">Número de Pedido:</span>
                    <span class="detail-value">{{ $order->order_number }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Fecha de Creación:</span>
                    <span class="detail-value">{{ $order->created_at ? $order->created_at->format('d/m/Y H:i:s') : 'N/A' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Estado del Pedido:</span>
                    <span class="detail-value">{{ ucfirst($order->status) }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Estado de Pago:</span>
                    <span class="detail-value">{{ ucfirst($order->payment_status ?? 'completed') }}</span>
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
                <p class="total"><strong>Total:</strong> ${{ number_format($order->total_amount * $order->exchange_rate, 2) }} {{ 'MXN' }}</p>
            </div>

            <div class="info-box">
                <h3>👤 Información del Cliente</h3>
                <div class="detail-row">
                    <span class="detail-label">Nombre:</span>
                    <span class="detail-value">{{ $customer->name }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value">{{ $customer->email }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Teléfono:</span>
                    <span class="detail-value">{{ $customer->phone ?? 'N/A' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Fecha de Registro:</span>
                    <span class="detail-value">{{ $customer->created_at ? $customer->created_at->format('d/m/Y') : 'N/A' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">País:</span>
                    <span class="detail-value">{{ $customer->country ?? 'N/A' }}</span>
                </div>
            </div>

            @if(isset($billing_info) && $billing_info)
            <div class="info-box">
                <h3>🧾 Datos de Facturación</h3>
                <div class="detail-row">
                    <span class="detail-label">RFC:</span>
                    <span class="detail-value">{{ $billing_info->rfc }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Razón Social:</span>
                    <span class="detail-value">{{ $billing_info->company_name ?? $billing_info->organization }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Código Postal:</span>
                    <span class="detail-value">{{ $billing_info->postal_code }}</span>
                </div>
                @if($billing_info->city)
                <div class="detail-row">
                    <span class="detail-label">Ciudad:</span>
                    <span class="detail-value">{{ $billing_info->city }}</span>
                </div>
                @endif
                @if($billing_info->state)
                <div class="detail-row">
                    <span class="detail-label">Estado:</span>
                    <span class="detail-value">{{ $billing_info->state }}</span>
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
                @if($billing_info->cfdiUsage)
                <div class="detail-row">
                    <span class="detail-label">Uso CFDI:</span>
                    <span class="detail-value">{{ $billing_info->cfdiUsage->name }}</span>
                </div>
                @endif
            </div>
            @elseif($order->billing_information)
            <div class="info-box">
                <h3>🧾 Datos de Facturación</h3>
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

            <h3>🛒 Productos Adquiridos</h3>
            @if(isset($items) && $items)
            @foreach($items as $item)
            <div class="product-item">
                <h4>{{ $item->product_title ?? $item->product->ProductTitle ?? 'Producto sin nombre' }}</h4>
                <div class="detail-row">
                    <span class="detail-label">SKU:</span>
                    <span class="detail-value">{{ $item->sku_id ?? 'N/A' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Cantidad:</span>
                    <span class="detail-value">{{ $item->quantity }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Precio unitario:</span>
                    <span class="detail-value">${{ number_format($item->unit_price, 2) }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total del producto:</span>
                    <span class="detail-value">${{ number_format($item->line_total, 2) }}</span>
                </div>
            </div>
            @endforeach
            @endif

            @if(isset($microsoft_account) && $microsoft_account)
            <div class="microsoft-info">
                <h3>🔑 Cuenta Microsoft Asociada</h3>
                <div class="detail-row">
                    <span class="detail-label">Dominio:</span>
                    <span class="detail-value">{{ $microsoft_account->domain_concatenated ?? $microsoft_account->domain }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Microsoft ID:</span>
                    <span class="detail-value">{{ $microsoft_account->microsoft_id ?? 'Pendiente' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Estado:</span>
                    <span class="detail-value">{{ $microsoft_account->is_active ? 'Activa' : 'Pendiente' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Es Default:</span>
                    <span class="detail-value">{{ $microsoft_account->is_default ? 'Sí' : 'No' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Nombre de la Empresa:</span>
                    <span class="detail-value">{{ $microsoft_account->company_name }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">País:</span>
                    <span class="detail-value">{{ $microsoft_account->country }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Ciudad:</span>
                    <span class="detail-value">{{ $microsoft_account->city }}</span>
                </div>
            </div>
            @elseif($order->microsoftAccount)
            <div class="microsoft-info">
                <h3>🔑 Cuenta de Microsoft</h3>
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

            <div class="info-box">
                <h3>💰 Resumen Financiero</h3>
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
                @if($order->discount_amount && $order->discount_amount > 0)
                <div class="detail-row">
                    <span class="detail-label">Descuento:</span>
                    <span class="detail-value">-${{ number_format($order->discount_amount, 2) }}</span>
                </div>
                @endif
                <div class="detail-row" style="border-top: 2px solid #dc3545; margin-top: 10px; padding-top: 10px;">
                    <span class="detail-label total">Total Final:</span>
                    <span class="detail-value total">${{ number_format($order->total_amount, 2) }} {{ $order->currency->code ?? 'MXN' }}</span>
                </div>
            </div>

            <div class="action-required">
                <h3>🎯 ACCIONES REQUERIDAS</h3>
                <ul>
                    <li><strong>Verificar:</strong> Transacción exitosa y datos de pago</li>
                    <li><strong>Confirmar:</strong> Aprovisionamiento de productos Microsoft</li>
                    <li><strong>Validar:</strong> Creación exitosa de cuenta Microsoft (si aplica)</li>
                    <li><strong>Contactar:</strong> Cliente para confirmar recepción</li>
                    <li><strong>Facturar:</strong> Generar factura si se requiere</li>
                    <li><strong>Seguimiento:</strong> Monitorear el estado del pedido en Partner Center</li>
                    <li><strong>Enviar:</strong> Credenciales de acceso al cliente</li>
                </ul>
            </div>

            <div class="alert">
                <h4>⚠️ IMPORTANTE</h4>
                <p><strong>Procesar inmediatamente:</strong> Este pedido requiere atención para completar el aprovisionamiento de productos Microsoft.</p>
                <p><strong>Referencia de seguimiento:</strong> {{ $order->order_number }}</p>
                @if(isset($paymentData['reference']))
                <p><strong>Referencia de pago:</strong> {{ $paymentData['reference'] }}</p>
                @endif
            </div>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} ReadyMarket</p>
        </div>
    </div>
</body>
</html>
