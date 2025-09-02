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
