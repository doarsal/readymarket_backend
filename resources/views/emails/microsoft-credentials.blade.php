<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $full_name }} - Credenciales Microsoft</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #0078d4 0%, #106ebe 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .content {
            padding: 30px;
        }
        .credentials-box {
            background-color: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .credential-item {
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .credential-item:last-child {
            border-bottom: none;
        }
        .credential-label {
            font-weight: bold;
            color: #495057;
            display: inline-block;
            width: 120px;
        }
        .credential-value {
            color: #0078d4;
            font-family: 'Courier New', monospace;
            background-color: #e7f3ff;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #dee2e6;
        }
        .logo {
            height: 40px;
            margin: 0 10px;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #0078d4;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
        }
        .microsoft-logo {
            color: #0078d4;
            font-size: 24px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0; font-size: 24px;">
                <span class="microsoft-logo">Microsoft</span> Account Created
            </h1>
            <p style="margin: 10px 0 0 0; opacity: 0.9;">ReadyMarket Platform</p>
        </div>

        <div class="content">
            <h2 style="color: #333; margin-top: 0;">Hola {{ $full_name }},</h2>

            <p>Tu cuenta Microsoft ha sido creada exitosamente. A continuación encontrarás los datos de acceso:</p>

            <div class="credentials-box">
                <h3 style="margin-top: 0; color: #0078d4;">📋 Datos de Acceso</h3>

                <div class="credential-item">
                    <span class="credential-label">URL de Acceso:</span>
                    <a href="{{ $microsoft_url }}" class="credential-value">{{ $microsoft_url }}</a>
                </div>

                <div class="credential-item">
                    <span class="credential-label">Usuario Admin:</span>
                    <span class="credential-value">{{ $admin_email }}</span>
                </div>

                <div class="credential-item">
                    <span class="credential-label">Contraseña:</span>
                    <span class="credential-value">{{ $password }}</span>
                </div>

                <div class="credential-item">
                    <span class="credential-label">Dominio:</span>
                    <span class="credential-value">{{ $domain }}</span>
                </div>

                <div class="credential-item">
                    <span class="credential-label">Organización:</span>
                    <span class="credential-value">{{ $organization }}</span>
                </div>
            </div>

            <div class="warning">
                <strong>⚠️ Importante:</strong> Guarda estos datos en un lugar seguro. ReadyMarket no almacena las contraseñas de Microsoft por seguridad.
            </div>

            <a href="{{ $microsoft_url }}" class="button">🚀 Acceder a Microsoft Admin Center</a>

            <h3 style="color: #333;">Próximos pasos:</h3>
            <ol style="color: #666; line-height: 1.6;">
                <li>Accede al Microsoft Admin Center usando las credenciales proporcionadas</li>
                <li>Configura usuarios adicionales según tus necesidades</li>
                <li>Explora los servicios disponibles en tu tenant</li>
                <li>Regresa a ReadyMarket para gestionar tus suscripciones</li>
            </ol>

            <p style="color: #666; font-style: italic;">
                Si tienes alguna pregunta o necesitas asistencia, no dudes en contactar a nuestro equipo de soporte.
            </p>
        </div>

        <div class="footer">
            <p style="margin: 0; color: #666;">
                <strong>ReadyMarket©</strong> |
                <a href="https://simplesystems.mx/readymarketV4/mx/aviso-de-privacidad" style="color: #0078d4;">Aviso de privacidad</a>
            </p>
            <p style="margin: 10px 0 0 0; font-size: 12px; color: #999;">
                Este correo fue enviado automáticamente. Por favor no responder.
            </p>
        </div>
    </div>
</body>
</html>
