<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Log\LogManager;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class LoggingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configurar la codificación UTF-8 para los logs
        $this->app->extend('log', function (LogManager $logManager) {
            $logManager->extend('utf8_single', function ($app, $config) {
                $handler = new StreamHandler(
                    $config['path'],
                    $config['level'] ?? 'debug'
                );

                // Crear un formatter que preserve UTF-8
                $formatter = new LineFormatter(
                    null, // usar formato por defecto
                    null, // usar formato de fecha por defecto
                    true, // permitir inline line breaks
                    true  // ignorar empty context and extra
                );

                $handler->setFormatter($formatter);

                return new \Monolog\Logger('laravel', [$handler]);
            });

            return $logManager;
        });
    }
}
