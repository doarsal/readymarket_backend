<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\Payment\MitecPaymentService;
use App\Services\Payment\MitecXmlBuilderService;
use App\Services\Payment\MitecEncryptionService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar servicios de MITEC
        $this->app->singleton(MitecXmlBuilderService::class);
        $this->app->singleton(MitecEncryptionService::class);
        $this->app->singleton(MitecPaymentService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configurar zona horaria por defecto
        date_default_timezone_set(config('app.timezone'));

        // Configurar Carbon para usar la zona horaria por defecto
        \Carbon\Carbon::setLocale('es');

        // Configurar UTF-8 para toda la aplicación
        mb_internal_encoding('UTF-8');
        mb_http_output('UTF-8');
        mb_regex_encoding('UTF-8');

        // Configurar locale para español mexicano
        setlocale(LC_TIME, 'es_MX.UTF-8', 'es_MX', 'Spanish_Mexico');

        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('payment-cards', function (Request $request) {
            return $request->user()
                        ? Limit::perMinute(10)->by($request->user()->id)
                        : Limit::perMinute(5)->by($request->ip());
        });
    }
}
