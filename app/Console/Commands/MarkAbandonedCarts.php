<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cart;
use Carbon\Carbon;

class MarkAbandonedCarts extends Command
{
    protected $signature = 'carts:mark-abandoned {--hours=24 : Horas de inactividad para considerar abandonado}';
    protected $description = 'Marca carritos como abandonados después de cierto tiempo de inactividad';

    public function handle()
    {
        $hours = $this->option('hours');
        $cutoffTime = Carbon::now()->subHours($hours);

        $abandonedCount = Cart::where('status', 'active')
            ->where('updated_at', '<', $cutoffTime)
            ->update(['status' => 'abandoned']);

        $this->info("Se marcaron {$abandonedCount} carritos como abandonados (inactivos por más de {$hours} horas)");

        return 0;
    }
}
