<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPendingMicrosoftAccountJob;
use App\Models\MicrosoftAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessPendingMicrosoftAccountsCommand extends Command
{
    protected $signature = 'microsoft-accounts:process-pending
                           {--limit=10 : Maximum number of accounts to process}
                           {--older-than=30 : Process accounts older than X minutes}';

    protected $description = 'Process pending Microsoft accounts that failed to create in Partner Center';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $olderThanMinutes = (int) $this->option('older-than');

        $this->info("Processing pending Microsoft accounts...");
        $this->info("Limit: {$limit}, Older than: {$olderThanMinutes} minutes");

        // Obtener cuentas pendientes
        $pendingAccounts = MicrosoftAccount::where('is_pending', true)
            ->where('is_active', false)
            ->where('created_at', '<=', now()->subMinutes($olderThanMinutes))
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        if ($pendingAccounts->isEmpty()) {
            $this->info('No pending accounts found to process.');
            return Command::SUCCESS;
        }

        $this->info("Found {$pendingAccounts->count()} pending accounts to process.");

        $processed = 0;
        $failed = 0;

        foreach ($pendingAccounts as $account) {
            try {
                $this->line("Processing account ID {$account->id} ({$account->domain_concatenated})...");

                // Dispatch job para procesar la cuenta
                ProcessPendingMicrosoftAccountJob::dispatch($account);

                $processed++;
                $this->info("✓ Queued account {$account->id}");

            } catch (\Exception $e) {
                $failed++;
                $this->error("✗ Failed to queue account {$account->id}: " . $e->getMessage());

                Log::error('Microsoft Account Command: Failed to queue account', [
                    'account_id' => $account->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("- Queued for processing: {$processed}");
        if ($failed > 0) {
            $this->error("- Failed to queue: {$failed}");
        }

        return Command::SUCCESS;
    }
}
