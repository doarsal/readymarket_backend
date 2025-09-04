<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a ReadyMarket - Verifica tu cuenta</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px 20px;
            text-align: center;
            color: white;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .content {
            padding: 40px 30px;
        }

        .welcome-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .welcome-section h2 {
            color: #333;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .welcome-section p {
            font-size: 16px;
            color: #666;
            margin-bottom: 20px;
        }

        .otp-section {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
            color: white;
        }

        .otp-section h3 {
            font-size: 20px;
            margin-bottom: 15px;
        }

        .otp-code {
            font-size: 36px;
            font-weight: bold;
            letter-spacing: 8px;
            background: rgba(255,255,255,0.2);
            padding: 15px 25px;
            border-radius: 8px;
            display: inline-block;
            margin: 20px 0;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .otp-section p {
            font-size: 14px;
            opacity: 0.9;
        }

        .instructions {
            background-color: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 30px 0;
            border-radius: 0 8px 8px 0;
        }

        .instructions h4 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .instructions ol {
            padding-left: 20px;
        }

        .instructions li {
            margin-bottom: 8px;
            color: #666;
        }

        .security-notice {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
        }

        .security-notice h4 {
            color: #856404;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .security-notice h4::before {
            content: "🔒";
            margin-right: 8px;
        }

        .security-notice p {
            color: #856404;
            font-size: 14px;
        }

        .benefits {
            margin: 30px 0;
        }

        .benefits h4 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
            font-size: 20px;
        }

        .benefit-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .benefit-icon {
            font-size: 24px;
            margin-right: 15px;
            width: 40px;
            text-align: center;
        }

        .benefit-text {
            flex: 1;
        }

        .benefit-text h5 {
            color: #333;
            margin-bottom: 5px;
        }

        .benefit-text p {
            color: #666;
            font-size: 14px;
        }

        .footer {
            background-color: #333;
            color: white;
            padding: 30px;
            text-align: center;
        }

        .footer h4 {
            margin-bottom: 15px;
        }

        .footer p {
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 10px;
        }

        .social-links {
            margin: 20px 0;
        }

        .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: white;
            text-decoration: none;
            font-size: 18px;
        }

        .expiry-timer {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            margin: 20px 0;
            border: 1px solid #ff9a9e;
        }

        .expiry-timer h5 {
            color: #d63384;
            margin-bottom: 5px;
            font-size: 16px;
        }

        .expiry-timer p {
            color: #d63384;
            font-size: 14px;
            font-weight: 500;
        }

        @media (max-width: 600px) {
            .container {
                margin: 10px;
                border-radius: 0;
            }

            .content {
                padding: 20px 15px;
            }

            .otp-code {
                font-size: 28px;
                letter-spacing: 4px;
                padding: 12px 20px;
            }

            .header h1 {
                font-size: 24px;
            }

            .welcome-section h2 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>¡Bienvenido a ReadyMarket!</h1>
            <p>Tu marketplace de soluciones empresariales</p>
        </div>

        <!-- Main Content -->
        <div class="content">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <h2>¡Hola {{ $user->name }}!</h2>
                <p>Gracias por unirte a ReadyMarket. Estamos emocionados de tenerte como parte de nuestra comunidad.</p>
                <p>Para completar tu registro y asegurar tu cuenta, necesitamos verificar tu dirección de correo electrónico.</p>
            </div>

            <!-- OTP Section -->
            <div class="otp-section">
                <h3>Tu código de verificación</h3>
                <div class="otp-code">{{ $otpCode }}</div>
                <p>Ingresa este código en la página de verificación para activar tu cuenta</p>
            </div>

            <!-- Expiry Timer -->
            <div class="expiry-timer">
                <h5>⏰ Tiempo límite</h5>
                <p>Este código expira en 10 minutos por tu seguridad</p>
            </div>

            <!-- Instructions -->
            <div class="instructions">
                <h4>Cómo verificar tu cuenta:</h4>
                <ol>
                    <li>Regresa a la página de verificación en ReadyMarket</li>
                    <li>Ingresa el código de 6 dígitos que aparece arriba</li>
                    <li>Haz clic en "Verificar cuenta"</li>
                    <li>¡Listo! Tu cuenta estará activada</li>
                </ol>
            </div>

            <!-- Security Notice -->
            <div class="security-notice">
                <h4>Aviso de Seguridad</h4>
                <p>Este código es personal e intransferible. Nunca lo compartas con nadie. Si no solicitaste este registro, puedes ignorar este correo.</p>
            </div>

            <!-- Benefits Section -->
            <div class="benefits">
                <h4>¿Qué puedes hacer en ReadyMarket?</h4>

                <div class="benefit-item">
                    <div class="benefit-icon">🛒</div>
                    <div class="benefit-text">
                        <h5>Compra productos empresariales</h5>
                        <p>Accede a una amplia gama de soluciones de Microsoft y otros proveedores</p>
                    </div>
                </div>

                <div class="benefit-item">
                    <div class="benefit-icon">⚡</div>
                    <div class="benefit-text">
                        <h5>Procesamiento rápido</h5>
                        <p>Activación automática y entrega inmediata de tus productos digitales</p>
                    </div>
                </div>

                <div class="benefit-item">
                    <div class="benefit-icon">🔒</div>
                    <div class="benefit-text">
                        <h5>Compras seguras</h5>
                        <p>Plataforma protegida con los más altos estándares de seguridad</p>
                    </div>
                </div>

                <div class="benefit-item">
                    <div class="benefit-icon">💼</div>
                    <div class="benefit-text">
                        <h5>Gestión empresarial</h5>
                        <p>Administra todas tus cuentas y licencias desde un solo lugar</p>
                    </div>
                </div>

                <div class="benefit-item">
                    <div class="benefit-icon">🎯</div>
                    <div class="benefit-text">
                        <h5>Soporte especializado</h5>
                        <p>Atención personalizada para resolver todas tus dudas</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <h4>ReadyMarket</h4>
            <p>Tu socio confiable en soluciones empresariales</p>
            <p>¿Tienes preguntas? Contáctanos en soporte@readymarket.mx</p>

            <div class="social-links">
                <a href="#" title="Facebook">📘</a>
                <a href="#" title="Twitter">🐦</a>
                <a href="#" title="LinkedIn">💼</a>
                <a href="#" title="WhatsApp">💬</a>
            </div>

            <p style="font-size: 12px; margin-top: 20px;">
                Este correo fue enviado automáticamente. Por favor no respondas a este mensaje.
            </p>
        </div>
    </div>
</body>
</html>
