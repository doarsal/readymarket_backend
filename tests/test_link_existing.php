<?php

/**
 * Script de prueba para el flujo completo de linkExisting
 * Prueba la vinculación de cuentas Microsoft existentes
 */

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\Log;

// Cargar la aplicación Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "==============================================\n";
echo "PRUEBA DE FLUJO: LINK EXISTING MICROSOFT ACCOUNT\n";
echo "==============================================\n\n";

// Configuración de prueba
$baseUrl = 'http://127.0.0.1:8000/api/v1';
$testEmail = 'admin@test.com';
$testPassword = 'password';

echo "PASO 1: Autenticación del usuario\n";
echo "-----------------------------------\n";

// Login
$loginData = [
    'email' => $testEmail,
    'password' => $testPassword
];

$ch = curl_init($baseUrl . '/auth/login');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($loginData),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "❌ Error en login: HTTP $httpCode\n";
    echo "Response: $response\n";
    exit(1);
}

$loginResponse = json_decode($response, true);
if (!isset($loginResponse['access_token'])) {
    echo "❌ No se obtuvo token de acceso\n";
    echo "Response: $response\n";
    exit(1);
}

$token = $loginResponse['access_token'];
echo "✅ Login exitoso\n";
echo "Token: " . substr($token, 0, 20) . "...\n\n";

// ESCENARIOS DE PRUEBA
$scenarios = [
    [
        'name' => 'Cuenta que EXISTE en Partner Center',
        'domain' => 'empresareal.com', // Cambia esto por un dominio real de tu Partner Center
        'email' => 'admin@empresareal.onmicrosoft.com',
        'expected_pending' => false,
        'expected_active' => true
    ],
    [
        'name' => 'Cuenta que NO EXISTE en Partner Center',
        'domain' => 'empresanueva' . time() . '.com',
        'email' => 'admin@empresanueva' . time() . '.onmicrosoft.com',
        'expected_pending' => true,
        'expected_active' => false
    ]
];

foreach ($scenarios as $index => $scenario) {
    $scenarioNum = $index + 1;

    echo "\n==============================================\n";
    echo "ESCENARIO $scenarioNum: {$scenario['name']}\n";
    echo "==============================================\n\n";

    echo "PASO 2: Verificar disponibilidad del dominio\n";
    echo "--------------------------------------------\n";

    $checkData = ['domain' => $scenario['domain']];

    $ch = curl_init($baseUrl . '/microsoft-accounts/check-domain');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($checkData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $checkResponse = json_decode($response, true);

    if ($httpCode === 200 && isset($checkResponse['available']) && $checkResponse['available']) {
        echo "✅ Dominio disponible: {$checkResponse['domain_concatenated']}\n\n";
    } else {
        echo "⚠️  Dominio no disponible o error en validación\n";
        echo "Response: $response\n";
        continue; // Saltar este escenario
    }

    echo "PASO 3: Vincular cuenta existente\n";
    echo "----------------------------------\n";

    $linkData = [
        'domain' => $scenario['domain'],
        'global_admin_email' => $scenario['email']
    ];

    echo "Request data:\n";
    echo json_encode($linkData, JSON_PRETTY_PRINT) . "\n\n";

    $ch = curl_init($baseUrl . '/microsoft-accounts/link-existing');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($linkData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    echo "HTTP Code: $httpCode\n";

    if ($curlError) {
        echo "❌ Error CURL: $curlError\n";
        continue;
    }

    $linkResponse = json_decode($response, true);

    if (!$linkResponse) {
        echo "❌ Error al parsear respuesta JSON\n";
        echo "Response: $response\n";
        continue;
    }

    echo "\nResponse:\n";
    echo json_encode($linkResponse, JSON_PRETTY_PRINT) . "\n\n";

    // Validar respuesta
    if ($httpCode === 201 && isset($linkResponse['success']) && $linkResponse['success']) {
        echo "✅ Cuenta vinculada exitosamente\n\n";

        $accountData = $linkResponse['data'] ?? [];

        echo "VALIDACIÓN DE DATOS:\n";
        echo "-------------------\n";

        // Validar campos
        $validations = [
            'microsoft_id' => $accountData['microsoft_id'] ?? null,
            'domain' => $accountData['domain'] ?? null,
            'domain_concatenated' => $accountData['domain_concatenated'] ?? null,
            'global_admin_email' => $accountData['global_admin_email'] ?? null,
            'first_name' => $accountData['first_name'] ?? null,
            'last_name' => $accountData['last_name'] ?? null,
            'organization' => $accountData['organization'] ?? null,
            'is_pending' => $accountData['is_pending'] ?? null,
            'is_active' => $accountData['is_active'] ?? null,
            'account_type' => $accountData['account_type'] ?? null,
        ];

        foreach ($validations as $field => $value) {
            $status = $value !== null ? '✅' : '❌';
            echo "$status $field: " . json_encode($value) . "\n";
        }

        echo "\nVALIDACIONES ESPERADAS:\n";
        echo "----------------------\n";

        // Validar estado esperado
        $pendingMatch = $accountData['is_pending'] === $scenario['expected_pending'];
        $activeMatch = $accountData['is_active'] === $scenario['expected_active'];

        echo ($pendingMatch ? '✅' : '❌') . " is_pending esperado: " . json_encode($scenario['expected_pending']) .
             " - obtenido: " . json_encode($accountData['is_pending']) . "\n";
        echo ($activeMatch ? '✅' : '❌') . " is_active esperado: " . json_encode($scenario['expected_active']) .
             " - obtenido: " . json_encode($accountData['is_active']) . "\n";

        // Validar que NO sea ID temporal si está activo
        if ($accountData['is_active']) {
            $isRealId = !str_contains($accountData['microsoft_id'], 'pending-link-');
            echo ($isRealId ? '✅' : '❌') . " microsoft_id NO debe ser temporal: " . $accountData['microsoft_id'] . "\n";

            $hasRealData = $accountData['first_name'] !== 'Pending' && $accountData['last_name'] !== 'Verification';
            echo ($hasRealData ? '✅' : '❌') . " Debe tener datos reales (no Pending/Verification)\n";
        } else {
            $isTempId = str_contains($accountData['microsoft_id'], 'pending-link-');
            echo ($isTempId ? '✅' : '❌') . " microsoft_id DEBE ser temporal: " . $accountData['microsoft_id'] . "\n";
        }

        echo "\nPróximos pasos sugeridos:\n";
        foreach ($linkResponse['next_steps'] ?? [] as $step) {
            echo "  → $step\n";
        }

        // Si está pendiente, probar verificación
        if ($accountData['is_pending']) {
            echo "\n\nPASO 4: Intentar verificar cuenta pendiente\n";
            echo "-------------------------------------------\n";

            $accountId = $accountData['id'];

            $ch = curl_init($baseUrl . "/microsoft-accounts/$accountId/verify-link");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'PATCH',
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $token
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $verifyResponse = json_decode($response, true);

            echo "HTTP Code: $httpCode\n";
            echo "Response:\n";
            echo json_encode($verifyResponse, JSON_PRETTY_PRINT) . "\n";

            if ($httpCode === 422) {
                echo "✅ Esperado: La cuenta aún no puede ser verificada (invitación no aceptada)\n";
            } elseif ($httpCode === 200) {
                echo "✅ Cuenta verificada y activada\n";
            }
        }

    } else {
        echo "❌ Error al vincular cuenta\n";
        echo "HTTP Code: $httpCode\n";
        echo "Message: " . ($linkResponse['message'] ?? 'No message') . "\n";
    }
}

echo "\n\n==============================================\n";
echo "PRUEBAS COMPLETADAS\n";
echo "==============================================\n";
echo "\nRevisa los logs en:\n";
echo "- storage/logs/laravel.log\n";
echo "- storage/logs/partner_center_" . date('Y-m-d') . ".log\n";
