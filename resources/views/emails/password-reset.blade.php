<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Restablecer Contraseña</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
            font-size: 24px;
        }
        .header img {
            max-width: 200px;
            margin-bottom: 10px;
        }
        .content {
            margin-bottom: 30px;
        }
        .greeting {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 20px;
        }
        .message {
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .reset-button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            font-size: 16px;
        }
        .reset-button:hover {
            background-color: #0056b3;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .warning-icon {
            color: #856404;
            font-weight: bold;
        }
        .footer {
            border-top: 2px solid #e9ecef;
            padding-top: 20px;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
        }
        .security-info {
            background-color: #f8f9fa;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            margin: 20px 0;
        }
        .security-info h3 {
            color: #17a2b8;
            margin-top: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>{{ config('app.name') }}</h1>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">¡Hola {{ $user_name }}!</div>

            <div class="message">
                Recibiste este correo porque solicitaste restablecer la contraseña de tu cuenta en {{ config('app.name') }}.
            </div>

            <div class="button-container">
                <a href="{{ $reset_url }}" class="reset-button">
                    Restablecer Contraseña
                </a>
            </div>

            <div class="warning">
                <span class="warning-icon">⚠️</span>
                <strong>Importante:</strong> Este enlace expirará en <strong>60 minutos</strong> por seguridad.
            </div>

            <div class="security-info">
                <h3>🔒 Información de Seguridad</h3>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Si no solicitaste este restablecimiento, puedes ignorar este correo</li>
                    <li>Tu contraseña actual permanece segura hasta que uses este enlace</li>
                    <li>Este enlace solo puede ser usado una vez</li>
                    <li>Para mayor seguridad, cierra tu navegador después de cambiar la contraseña</li>
                </ul>
            </div>

            <div class="message">
                Si tienes problemas haciendo clic en el botón, puedes copiar y pegar el siguiente enlace en tu navegador:
            </div>

            <div style="background-color: #f8f9fa; padding: 10px; border-radius: 5px; word-break: break-all; font-family: monospace; font-size: 12px;">
                {{ $reset_url }}
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Este correo fue enviado automáticamente por {{ config('app.name') }}.</p>
            <p>Si necesitas ayuda, puedes contactarnos respondiendo a este correo.</p>
            <p style="margin-top: 20px;">
                <strong>{{ config('app.name') }}</strong><br>
                Sistema de Gestión de Cuentas Microsoft
            </p>
        </div>
    </div>
</body>
</html>
