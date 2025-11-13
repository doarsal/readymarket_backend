<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Programar limpieza automática de tokens expirados
Schedule::command('security:clean-expired-tokens --force')
    ->daily()
    ->at('02:00')
    ->withoutOverlapping()
    ->runInBackground();

// Comando para limpiar tokens muy antiguos (más de 60 días)
Schedule::command('security:clean-expired-tokens --force --days=60')
    ->weekly()
    ->sundays()
    ->at('03:00')
    ->withoutOverlapping();

// Sincronizar AvailabilityIds de productos desde Microsoft Partner Center
// Se ejecuta semanalmente los lunes a las 4:00 AM
Schedule::command('products:sync-availabilities')
    ->weekly()
    ->mondays()
    ->at('04:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Product availabilities sync scheduled task completed successfully');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Product availabilities sync scheduled task failed');
    });
