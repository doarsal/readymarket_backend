<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Error en creación de cuenta Microsoft</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #d32f2f; color: white; padding: 15px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .error-box { background-color: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin: 15px 0; }
        .account-info { background-color: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 15px 0; }
        .contact-info { background-color: #f3e5f5; border-left: 4px solid #9c27b0; padding: 15px; margin: 15px 0; }
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
            <h1>🚨 Error en creación de cuenta Microsoft</h1>
        </div>

        <div class="content">
            <p><strong>Se ha detectado un error al crear una cuenta Microsoft en Partner Center.</strong></p>

            <div class="account-info">
                <h3>🏢 Información de la Cuenta</h3>
                <p><strong>ID Local:</strong> {{ $microsoftAccount->id }}</p>
                <p><strong>User ID:</strong> {{ $microsoftAccount->user_id }}</p>
                <p><strong>Microsoft ID:</strong> {{ $microsoftAccount->microsoft_id ?: 'N/A - Error en creación' }}</p>
                <p><strong>Organización:</strong> {{ $microsoftAccount->organization }}</p>
                <p><strong>Dominio Base:</strong> {{ $microsoftAccount->domain }}</p>
                <p><strong>Dominio Completo:</strong> {{ $microsoftAccount->domain_concatenated }}</p>
                <p><strong>Fecha de Creación:</strong> {{ $microsoftAccount->created_at ? $microsoftAccount->created_at->format('d/m/Y H:i:s') : 'N/A' }}</p>
                <p><strong>Última Actualización:</strong> {{ $microsoftAccount->updated_at ? $microsoftAccount->updated_at->format('d/m/Y H:i:s') : 'N/A' }}</p>
                @if($microsoftAccount->configuration_id)
                    <p><strong>Configuration ID:</strong> {{ $microsoftAccount->configuration_id }}</p>
                @endif
                @if($microsoftAccount->store_id)
                    <p><strong>Store ID:</strong> {{ $microsoftAccount->store_id }}</p>
                @endif
            </div>

            <div class="contact-info">
                <h3>👤 Información del Contacto</h3>
                <p><strong>Nombre:</strong> {{ $microsoftAccount->first_name }} {{ $microsoftAccount->last_name }}</p>
                <p><strong>Email:</strong> {{ $microsoftAccount->email }}</p>
                @if($microsoftAccount->phone)
                    <p><strong>Teléfono:</strong> {{ $microsoftAccount->phone }}</p>
                @endif
                @if($microsoftAccount->address)
                    <p><strong>Dirección:</strong> {{ $microsoftAccount->address }}</p>
                @endif
                @if($microsoftAccount->city)
                    <p><strong>Ciudad:</strong> {{ $microsoftAccount->city }}</p>
                @endif
                @if($microsoftAccount->state_code)
                    <p><strong>Estado (Código):</strong> {{ $microsoftAccount->state_code }}</p>
                @endif
                @if($microsoftAccount->state_name)
                    <p><strong>Estado (Nombre):</strong> {{ $microsoftAccount->state_name }}</p>
                @endif
                @if($microsoftAccount->postal_code)
                    <p><strong>Código Postal:</strong> {{ $microsoftAccount->postal_code }}</p>
                @endif
                <p><strong>País (Código):</strong> {{ $microsoftAccount->country_code }}</p>
                @if($microsoftAccount->country_name)
                    <p><strong>País (Nombre):</strong> {{ $microsoftAccount->country_name }}</p>
                @endif
                <p><strong>Código de Idioma:</strong> {{ $microsoftAccount->language_code }}</p>
                <p><strong>Cultura:</strong> {{ $microsoftAccount->culture }}</p>
            </div>

            <div class="error-box">
                <h3>❌ Error Reportado</h3>
                <p><strong>Mensaje:</strong> {{ $errorMessage }}</p>
                @if(!empty($errorDetails))
                    @if(isset($errorDetails['details']))
                        <p><strong>Detalles:</strong> {{ $errorDetails['details'] }}</p>
                    @endif
                    @if(isset($errorDetails['error_code']))
                        <p><strong>Código de Error:</strong> {{ $errorDetails['error_code'] }}</p>
                    @endif
                @endif
            </div>

            @if(!empty($microsoftErrorDetails))
                <div class="microsoft-error">
                    <h3>🔗 Detalles del Error de Microsoft</h3>

                    @if(isset($microsoftErrorDetails['error_code']))
                        <p><strong>Código de Error Microsoft:</strong> {{ $microsoftErrorDetails['error_code'] }}</p>
                    @endif

                    @if(isset($microsoftErrorDetails['description']))
                        <p><strong>Descripción:</strong> {{ $microsoftErrorDetails['description'] }}</p>
                    @endif

                    @if(isset($microsoftErrorDetails['http_status']))
                        <p><strong>HTTP Status:</strong> {{ $microsoftErrorDetails['http_status'] }}</p>
                    @endif

                    @if(isset($microsoftErrorDetails['correlation_id']))
                        <p><strong>Correlation ID:</strong> {{ $microsoftErrorDetails['correlation_id'] }}</p>
                    @endif

                    @if(isset($microsoftErrorDetails['request_id']))
                        <p><strong>Request ID:</strong> {{ $microsoftErrorDetails['request_id'] }}</p>
                    @endif

                    @if(isset($microsoftErrorDetails['raw_response']))
                        <h4>Respuesta Completa de Microsoft:</h4>
                        <div class="code-block">
                            {{ $microsoftErrorDetails['raw_response'] }}
                        </div>
                    @endif
                </div>
            @endif

            <div class="footer">
                <p class="timestamp">Timestamp: {{ $timestamp }}</p>
                <p>Este es un mensaje automático del sistema Readymarket.</p>
                <p>Por favor, revisa Microsoft Partner Center y toma las acciones necesarias.</p>
            </div>
        </div>
    </div>
</body>
</html>
