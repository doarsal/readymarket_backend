<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Cart;
use Carbon\Carbon;

class ProcessAbandonedCarts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $hoursInactive;

    public function __construct($hoursInactive = 24)
    {
        $this->hoursInactive = $hoursInactive;
    }

    public function handle()
    {
        $cutoffTime = Carbon::now()->subHours($this->hoursInactive);

        Cart::where('status', 'active')
            ->where('updated_at', '<', $cutoffTime)
            ->update(['status' => 'abandoned']);
    }
}
