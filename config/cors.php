<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:4173',  // Para Vite preview server (producción)
        'http://localhost:5173',  // Para Vite dev server
        'http://localhost:8080',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:4173',  // Para Vite preview server (producción)
        'http://127.0.0.1:5173',  // Para Vite dev server
        'http://127.0.0.1:8000',
        'http://127.0.0.1:8080',
        'http://localhost',
        'http://localhost/marketplace/old',
        'http://localhost:80',
        'http://127.0.0.1',
        'http://127.0.0.1:80',
        // URLs de producción
        'https://readymarket.readymind.mx',  // Frontend de producción
        'https://readymarket-backend.readymind.mx',  // Backend de producción
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-Cart-Token',
        'x-cart-token',
        'X-CSRF-TOKEN',
        'Origin',
        'Cache-Control',
        'X-Content-Type-Options',
    ],

    'exposed_headers' => ['X-Cart-Token', 'x-cart-token'],

    'max_age' => 3600, // Cache preflight por 1 hora (reducido de 24h)

    'supports_credentials' => true, // Cambiar a true para mejor seguridad con cookies

];
