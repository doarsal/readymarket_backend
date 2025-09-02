<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PaymentCard;
use Illuminate\Support\Facades\Cache;

class CleanExpiredCards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cards:clean-expired
                           {--force : Force cleanup without confirmation}
                           {--notify : Send notifications to users}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean expired payment cards by marking them as inactive';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Scanning for expired payment cards...');

        // Encontrar todas las tarjetas expiradas que aún están activas
        $expiredCards = PaymentCard::where('is_active', true)
                                  ->get()
                                  ->filter(function ($card) {
                                      return $card->is_expired;
                                  });

        if ($expiredCards->isEmpty()) {
            $this->info('✅ No expired cards found.');
            return 0;
        }

        $this->warn("Found {$expiredCards->count()} expired cards.");

        // Mostrar detalles si no es forzado
        if (!$this->option('force')) {
            $this->table(
                ['ID', 'User ID', 'Last 4 Digits', 'Brand', 'Expired Date', 'Days Expired'],
                $expiredCards->map(function ($card) {
                    return [
                        $card->id,
                        $card->user_id,
                        $card->last_four_digits,
                        $card->brand,
                        sprintf('%02d/%s', $card->expiry_month, $card->expiry_year),
                        abs($card->days_until_expiry) . ' days ago'
                    ];
                })
            );

            if (!$this->confirm('Do you want to mark these cards as inactive?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        // Procesar limpieza
        $cleanedCount = 0;
        $usersCleaned = [];

        foreach ($expiredCards as $card) {
            $card->update(['is_active' => false]);
            $cleanedCount++;
            $usersCleaned[] = $card->user_id;

            $this->line("🔒 Deactivated card {$card->id} (User {$card->user_id}) - {$card->brand} ****{$card->last_four_digits}");
        }

        // Limpiar cache de usuarios afectados
        $uniqueUsers = array_unique($usersCleaned);
        foreach ($uniqueUsers as $userId) {
            Cache::forget("payment_cards_user_{$userId}");
            Cache::forget("payment_cards_user_{$userId}_active");
        }

        $this->info("✅ Successfully deactivated {$cleanedCount} expired cards for " . count($uniqueUsers) . " users.");

        // Notificaciones (opcional)
        if ($this->option('notify')) {
            $this->info('📧 Notification feature not implemented yet.');
            // Aquí podrías implementar notificaciones por email
        }

        return 0;
    }
}
