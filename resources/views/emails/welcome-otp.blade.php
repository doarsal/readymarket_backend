<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de verificación - ReadyMarket</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .welcome-title {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        .welcome-text {
            font-size: 16px;
            color: #666;
            margin-bottom: 30px;
        }
        .otp-container {
            text-align: center;
            margin: 30px 0;
            padding: 25px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
        }
        .otp-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .otp-code {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            letter-spacing: 4px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
        }
        .otp-note {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }
        .instructions {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin: 25px 0;
            border-left: 4px solid #2c3e50;
        }
        .instructions h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 16px;
        }
        .instructions p {
            margin: 5px 0;
            font-size: 14px;
            color: #666;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 14px;
            color: #666;
        }
        .footer p {
            margin: 5px 0;
        }
        .security-note {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            font-size: 14px;
            color: #856404;
        }
        @media (max-width: 600px) {
            body {
                padding: 10px;
            }
            .container {
                padding: 20px;
            }
            .otp-code {
                font-size: 28px;
                letter-spacing: 2px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">ReadyMarket</div>
        </div>

        <h1 class="welcome-title">¡Bienvenido, {{ $user->first_name }}!</h1>

        <p class="welcome-text">
            Gracias por registrarte en ReadyMarket. Para completar tu registro y verificar tu cuenta, utiliza el siguiente código de verificación:
        </p>

        <div class="otp-container">
            <div class="otp-label">Tu código de verificación</div>
            <div class="otp-code">{{ $otpCode }}</div>
            <div class="otp-note">Este código expira en 10 minutos</div>
        </div>

        <div class="instructions">
            <h4>Cómo usar este código:</h4>
            <p>1. Regresa a la página de verificación</p>
            <p>2. Ingresa el código de 6 dígitos</p>
            <p>3. Haz clic en "Verificar código"</p>
        </div>

        <div class="security-note">
            <strong>Nota de seguridad:</strong> Si no solicitaste este código, puedes ignorar este email. Tu cuenta permanecerá segura.
        </div>

        <div class="footer">
            <p>Este email fue generado automáticamente el {{ $timestamp }}</p>
            <p>© {{ date('Y') }} ReadyMarket. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
