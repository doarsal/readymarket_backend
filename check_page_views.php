<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->boot();

use App\Models\PageView;

echo "=== Últimos registros de Page Views ===\n\n";

$recentViews = PageView::orderBy('created_at', 'desc')
    ->limit(3)
    ->get();

foreach ($recentViews as $view) {
    echo "ID: {$view->id}\n";
    echo "Página: {$view->page_type} - {$view->page_url}\n";
    echo "IP: {$view->visitor_ip}\n";
    echo "Sesión: {$view->session_id}\n";
    echo "País: " . ($view->country ?? 'null') . "\n";
    echo "Región: " . ($view->region ?? 'null') . "\n";
    echo "Ciudad: " . ($view->city ?? 'null') . "\n";
    echo "Zona horaria: " . ($view->timezone ?? 'null') . "\n";
    echo "Navegador: " . ($view->browser ?? 'null') . "\n";
    echo "SO: " . ($view->os ?? 'null') . "\n";
    echo "Dispositivo: " . ($view->device_type ?? 'null') . "\n";
    echo "Datos adicionales: " . json_encode($view->additional_data) . "\n";
    echo "Creado: {$view->created_at}\n";
    echo "----------------------------------------\n\n";
}

echo "Total de registros: " . PageView::count() . "\n";
