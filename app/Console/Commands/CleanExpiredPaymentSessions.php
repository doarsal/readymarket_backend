<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PaymentSession;

class CleanExpiredPaymentSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:clean-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean expired payment sessions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $deleted = PaymentSession::cleanExpired();

        $this->info("Cleaned {$deleted} expired payment sessions.");

        return 0;
    }
}
