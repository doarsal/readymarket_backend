<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Marketplace Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo h1 {
            font-size: 24px;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .btn-logout {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }
        .btn-logout:hover {
            background: rgba(255,255,255,0.3);
        }
        .main-content {
            padding: 30px 0;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stat-card h3 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
        }
        .stat-card p {
            color: #666;
            font-size: 16px;
        }
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .action-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .action-card h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .btn-action {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            transition: transform 0.2s;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .btn-action:hover {
            transform: translateY(-1px);
        }
        .security-status {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            border-top: 1px solid #e1e1e1;
            margin-top: 40px;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1>🛡️ Marketplace Admin</h1>
                </div>
                <div class="user-info">
                    <span>Bienvenido, <strong>{{ $user->full_name }}</strong></span>
                    <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn-logout">Cerrar Sesión</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="container">

            <div class="security-status">
                <h2>🔒 Sistema Seguro Activo</h2>
                <p>Todas las mejoras de seguridad han sido implementadas correctamente</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>{{ $stats['total_users'] }}</h3>
                    <p>Usuarios Totales</p>
                </div>
                <div class="stat-card">
                    <h3>{{ $stats['active_users'] }}</h3>
                    <p>Usuarios Activos</p>
                </div>
                <div class="stat-card">
                    <h3>{{ $stats['recent_logins'] }}</h3>
                    <p>Logins (7 días)</p>
                </div>
            </div>

            <div class="actions-grid">
                <div class="action-card">
                    <h3>📚 Documentación API</h3>
                    <p>Accede a la documentación completa de la API del marketplace</p>
                    <a href="/api/documentation" class="btn-action" target="_blank">Ver Documentación</a>
                </div>

                <div class="action-card">
                    <h3>👥 Gestión de Usuarios</h3>
                    <p>Administrar usuarios, roles y permisos del sistema</p>
                    <a href="/api/v1/users" class="btn-action" target="_blank">Ver Usuarios API</a>
                </div>

                <div class="action-card">
                    <h3>📊 Analytics</h3>
                    <p>Revisar métricas y estadísticas del sistema</p>
                    <a href="/api/v1/analytics/dashboard" class="btn-action" target="_blank">Ver Analytics</a>
                </div>

                <div class="action-card">
                    <h3>🔧 Configuración</h3>
                    <p>Configurar parámetros del sistema y marketplace</p>
                    <a href="/api/v1/settings" class="btn-action" target="_blank">Ver Configuración</a>
                </div>

                <div class="action-card">
                    <h3>🛡️ Seguridad</h3>
                    <p>Monitorear logs y eventos de seguridad</p>
                    <a href="javascript:void(0)" class="btn-action" onclick="alert('Logs disponibles en storage/logs/')">Ver Logs</a>
                </div>

                <div class="action-card">
                    <h3>🧹 Mantenimiento</h3>
                    <p>Herramientas de limpieza y optimización</p>
                    <a href="javascript:void(0)" class="btn-action" onclick="cleanTokens()">Limpiar Tokens</a>
                    <a href="javascript:void(0)" class="btn-action" onclick="clearCache()">Limpiar Cache</a>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; {{ date('Y') }} Marketplace Admin. Sistema protegido con mejores prácticas de seguridad.</p>
        </div>
    </footer>

    <script>
        function cleanTokens() {
            if (confirm('¿Estás seguro de que quieres limpiar los tokens expirados?')) {
                alert('Comando: php artisan security:clean-expired-tokens --force');
            }
        }

        function clearCache() {
            if (confirm('¿Estás seguro de que quieres limpiar el cache?')) {
                alert('Comandos: php artisan cache:clear && php artisan config:cache');
            }
        }
    </script>
</body>
</html>
