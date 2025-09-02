<?php

namespace App\Console\Commands;

use App\Services\CartService;
use Illuminate\Console\Command;

class CleanExpiredCartsCommand extends Command
{
    protected $signature = 'cart:clean-expired
                           {--dry-run : Show what would be cleaned without actually doing it}';

    protected $description = 'Clean expired carts from the database';

    public function handle(CartService $cartService): int
    {
        $this->info('Starting expired carts cleanup...');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made');
            // Implementar lógica de dry-run aquí
            return Command::SUCCESS;
        }

        try {
            $cleanedCount = $cartService->cleanExpiredCarts();

            $this->info("Successfully cleaned {$cleanedCount} expired carts.");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error cleaning expired carts: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
