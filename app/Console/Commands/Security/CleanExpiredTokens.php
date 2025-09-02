<?php

namespace App\Console\Commands\Security;

use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;
use Carbon\Carbon;

class CleanExpiredTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:clean-expired-tokens
                            {--force : Force cleanup without confirmation}
                            {--days=30 : Delete tokens older than specified days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired and old personal access tokens for security';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $force = $this->option('force');

        $cutoffDate = Carbon::now()->subDays($days);

        // Contar tokens a eliminar
        $expiredTokens = PersonalAccessToken::where('created_at', '<', $cutoffDate)
            ->orWhereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now());

        $count = $expiredTokens->count();

        if ($count === 0) {
            $this->info('No hay tokens expirados para limpiar.');
            return 0;
        }

        $this->info("Se encontraron {$count} tokens para limpiar.");

        if (!$force && !$this->confirm('¿Deseas continuar con la limpieza?')) {
            $this->info('Operación cancelada.');
            return 0;
        }

        // Eliminar tokens
        $deleted = $expiredTokens->delete();

        $this->info("✅ Se eliminaron {$deleted} tokens expirados exitosamente.");

        // Log de seguridad
        \Log::info('Limpieza de tokens ejecutada', [
            'tokens_deleted' => $deleted,
            'cutoff_date' => $cutoffDate,
            'executed_by' => 'console'
        ]);

        return 0;
    }
}
