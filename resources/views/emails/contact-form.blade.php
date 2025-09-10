<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nuevo mensaje de contacto</title>
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
        .content {
            margin-bottom: 30px;
        }
        .field {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
        .field-label {
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
            display: block;
        }
        .field-value {
            color: #333;
            word-wrap: break-word;
        }
        .message-content {
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
            white-space: pre-wrap;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Nuevo Mensaje de Contacto</h1>
            <p>Readymarket</p>
        </div>

        <div class="content">
            <div class="field">
                <span class="field-label">Nombre completo:</span>
                <span class="field-value">{{ $name }}</span>
            </div>

            <div class="field">
                <span class="field-label">Correo electrónico:</span>
                <span class="field-value">{{ $email }}</span>
            </div>

            <div class="field">
                <span class="field-label">Teléfono:</span>
                <span class="field-value">{{ $phone }}</span>
            </div>

            <div class="field">
                <span class="field-label">Asunto:</span>
                <span class="field-value">{{ $subject }}</span>
            </div>

            <div class="field">
                <span class="field-label">Mensaje:</span>
                <div class="message-content">{{ $contactMessage }}</div>
            </div>
        </div>

        <div class="footer">
            <p>Este mensaje fue enviado desde el formulario de contacto del Readymarket.</p>
            <p>Fecha: {{ date('d/m/Y H:i:s') }}</p>
        </div>
    </div>
</body>
</html>
