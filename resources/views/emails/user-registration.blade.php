<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Usuario Registrado</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            background: #28a745;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 10px 10px 0 0;
            margin: -20px -20px 20px -20px;
        }
        .section {
            margin-bottom: 25px;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 5px;
            background-color: #fafafa;
        }
        .section h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #28a745;
            padding-bottom: 5px;
        }
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            width: 150px;
            color: #555;
        }
        .info-value {
            flex: 1;
            color: #333;
        }
        .stats-section {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .timestamp {
            text-align: center;
            color: #666;
            font-style: italic;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .null-value {
            color: #999;
            font-style: italic;
        }
        .verified {
            color: #28a745;
            font-weight: bold;
        }
        .pending {
            color: #ffc107;
            font-weight: bold;
        }
        .welcome-badge {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            text-align: center;
            margin: 20px 0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Nuevo Usuario Registrado</h1>
            <p>Se ha registrado un nuevo usuario en Readymarket</p>
        </div>

        <div class="welcome-badge">
            ¡Bienvenido/a {{ $user->name }} a la familia Readymarket!
        </div>

        <!-- Información Personal -->
        <div class="section">
            <h3>👤 Información Personal</h3>
            <div class="info-row">
                <span class="info-label">Nombre Completo:</span>
                <span class="info-value">{{ $user->name }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span class="info-value">{{ $user->email }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Teléfono:</span>
                <span class="info-value {{ $user->phone ? '' : 'null-value' }}">
                    {{ $user->phone ?: 'No proporcionado' }}
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Email Verificado:</span>
                <span class="info-value {{ $user->email_verified_at ? 'verified' : 'pending' }}">
                    {{ $user->email_verified_at ? '✅ Verificado' : '⏳ Pendiente' }}
                </span>
            </div>
            @if($user->email_verified_at)
                <div class="info-row">
                    <span class="info-label">Verificado el:</span>
                    <span class="info-value">{{ $user->email_verified_at->format('d/m/Y H:i:s') }}</span>
                </div>
            @endif
        </div>

        <!-- Información Profesional -->
        <div class="section">
            <h3>💼 Información Profesional</h3>
            <div class="info-row">
                <span class="info-label">Empresa:</span>
                <span class="info-value {{ $user->company_name ? '' : 'null-value' }}">
                    {{ $user->company_name ?: 'No proporcionada' }}
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Cargo/Posición:</span>
                <span class="info-value {{ $user->position ? '' : 'null-value' }}">
                    {{ $user->position ?: 'No proporcionado' }}
                </span>
            </div>
        </div>

        <!-- Información de Ubicación -->
        <div class="section">
            <h3>📍 Información de Ubicación</h3>
            <div class="info-row">
                <span class="info-label">Ciudad:</span>
                <span class="info-value {{ $user->city ? '' : 'null-value' }}">
                    {{ $user->city ?: 'No proporcionada' }}
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Estado:</span>
                <span class="info-value {{ $user->state ? '' : 'null-value' }}">
                    {{ $user->state ?: 'No proporcionado' }}
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">País:</span>
                <span class="info-value {{ $user->country ? '' : 'null-value' }}">
                    {{ $user->country ?: 'No proporcionado' }}
                </span>
            </div>
        </div>

        <!-- Información del Sistema -->
        <div class="section">
            <h3>⚙️ Información del Sistema</h3>
            <div class="info-row">
                <span class="info-label">ID de Usuario:</span>
                <span class="info-value">{{ $user->id }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Fecha de Registro:</span>
                <span class="info-value">{{ $user->created_at->format('d/m/Y H:i:s') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Última Actualización:</span>
                <span class="info-value">{{ $user->updated_at->format('d/m/Y H:i:s') }}</span>
            </div>
        </div>

        <!-- Estadísticas (opcional para futuras mejoras) -->
        <div class="stats-section">
            <h3>📊 Acciones Sugeridas</h3>
            <ul style="margin: 0; padding-left: 20px;">
                @if(!$user->email_verified_at)
                    <li><strong>Verificación pendiente:</strong> El usuario aún no ha verificado su email</li>
                @endif
                <li><strong>Seguimiento:</strong> Considerar enviar un email de bienvenida personalizado</li>
                <li><strong>Revisión:</strong> Verificar la información del usuario en el panel administrativo</li>
                <li><strong>Activación:</strong> El usuario puede comenzar a usar la plataforma inmediatamente</li>
            </ul>
        </div>

        <div class="timestamp">
            📅 Notificación generada el {{ $timestamp }}<br>
            🚀 <strong>¡Readymarket sigue creciendo!</strong>
        </div>
    </div>
</body>
</html>
