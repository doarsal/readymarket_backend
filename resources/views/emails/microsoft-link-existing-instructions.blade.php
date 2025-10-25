<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instrucciones - Vincular Cuenta Microsoft</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #00A4EF 0%, #0078D4 100%);
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .header .microsoft-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .header .logo-square {
            width: 40px;
            height: 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 2px;
        }
        .header .logo-square div {
            width: 18px;
            height: 18px;
        }
        .content {
            padding: 30px 20px;
        }
        .greeting {
            font-size: 16px;
            margin-bottom: 20px;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #0078D4;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .step {
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .step-number {
            display: inline-block;
            background-color: #0078D4;
            color: #ffffff;
            width: 30px;
            height: 30px;
            line-height: 30px;
            border-radius: 50%;
            text-align: center;
            font-weight: bold;
            margin-right: 10px;
        }
        .step h3 {
            color: #0078D4;
            margin: 10px 0;
            font-size: 18px;
        }
        .step p {
            margin: 10px 0;
            color: #666;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #0078D4;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 0;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        .button:hover {
            background-color: #005a9e;
        }
        .note {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 12px;
            margin: 15px 0;
            color: #856404;
            font-size: 14px;
        }
        .requirements {
            background-color: #e7f3ff;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .requirements h4 {
            margin-top: 0;
            color: #2196f3;
        }
        .requirements ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .requirements li {
            margin: 5px 0;
        }
        .partner-info {
            background-color: #f1f8ff;
            border: 1px solid #c8e1ff;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
        .partner-info h4 {
            margin-top: 0;
            color: #0366d6;
        }
        .partner-info p {
            margin: 5px 0;
        }
        .footer {
            background-color: #f8f9fa;
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #e0e0e0;
        }
        .footer a {
            color: #0078D4;
            text-decoration: none;
        }
        .divider {
            border: 0;
            height: 1px;
            background: #e0e0e0;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="microsoft-logo">
                <div class="logo-square">
                    <div style="background-color: #f25022;"></div>
                    <div style="background-color: #7fba00;"></div>
                    <div style="background-color: #00a4ef;"></div>
                    <div style="background-color: #ffb900;"></div>
                </div>
                <h1>Microsoft Partner</h1>
            </div>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">Instrucciones para vincular tu cuenta</p>
        </div>

        <div class="content">
            <p class="greeting">Hola,</p>

            <p>Has iniciado el proceso para vincular tu cuenta de Microsoft existente con <strong>{{ $partner['partner_name'] }}</strong> como tu proveedor de soluciones en la nube.</p>

            <div class="info-box">
                <strong>📋 Información de tu cuenta:</strong><br>
                <strong>Dominio:</strong> {{ $domain }}<br>
                <strong>Email Global Admin:</strong> {{ $global_admin_email }}
            </div>

            <p>Para completar el proceso, sigue estos pasos:</p>

            <!-- Paso 1 -->
            <div class="step">
                <div>
                    <span class="step-number">1</span>
                    <h3 style="display: inline;">Verificar perfil de facturación</h3>
                </div>
                <p>Inicia sesión con tu cuenta de <strong>Global Admin</strong> y asegúrate de que tu perfil de cliente esté completo en el portal de Microsoft.</p>
                
                <a href="{{ $urls['billing_profile'] }}" class="button" target="_blank">
                    🔗 Abrir Perfil de Facturación
                </a>

                <div class="note">
                    <strong>⚠️ Nota:</strong> Puede tomar hasta 5 minutos para que se actualice después de realizar cambios en el perfil.
                </div>
            </div>

            <!-- Paso 2 -->
            <div class="step">
                <div>
                    <span class="step-number">2</span>
                    <h3 style="display: inline;">Aceptar invitación del Partner</h3>
                </div>
                <p>Una vez completado tu perfil, haz clic en el siguiente enlace para aceptar la invitación y autorizar a <strong>{{ $partner['partner_name'] }}</strong> como tu proveedor de soluciones en la nube de Microsoft.</p>
                
                <a href="{{ $urls['partner_invitation'] }}" class="button" target="_blank">
                    ✅ Aceptar Invitación de Partner
                </a>

                <div class="note">
                    <strong>⚠️ Importante:</strong> Se requiere usuario con permisos de <strong>Global Administrator</strong> para aceptar la relación de partner.
                </div>
            </div>

            <hr class="divider">

            <!-- Requisitos -->
            <div class="requirements">
                <h4>📌 Requisitos necesarios:</h4>
                <ul>
                    <li>✓ Cuenta Microsoft 365 activa</li>
                    <li>✓ Permisos de Global Administrator</li>
                    <li>✓ Perfil de facturación completado</li>
                    <li>✓ Acceso al portal de administración de Microsoft</li>
                </ul>
            </div>

            <!-- Información del Partner -->
            <div class="partner-info">
                <h4>📞 Información de Contacto del Partner</h4>
                <p><strong>Nombre:</strong> {{ $partner['partner_name'] }}</p>
                <p><strong>Email:</strong> <a href="mailto:{{ $partner['partner_email'] }}">{{ $partner['partner_email'] }}</a></p>
                <p><strong>Teléfono:</strong> {{ $partner['partner_phone'] }}</p>
                <p><strong>Partner ID:</strong> {{ $partner['partner_id'] }}</p>
            </div>

            <p style="margin-top: 30px;">Si tienes alguna duda o necesitas asistencia, no dudes en contactarnos.</p>

            <p style="margin-top: 20px;">
                Atentamente,<br>
                <strong>{{ $partner['partner_name'] }}</strong>
            </p>
        </div>

        <div class="footer">
            <p>
                <strong>ReadyMarket©</strong><br>
                Este correo fue enviado automáticamente, por favor no respondas a esta dirección.
            </p>
            <p>
                <a href="https://simplesystems.mx/readymarketV4/mx/aviso-de-privacidad">Aviso de Privacidad</a>
            </p>
        </div>
    </div>
</body>
</html>
