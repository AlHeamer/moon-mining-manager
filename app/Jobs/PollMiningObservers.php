<?php

namespace App\Jobs;

use App\Refinery;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollMiningObservers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Grab all of the refineries and loop through them.
        $refineries = Refinery::whereNotNull('corporation_id')->get();
        $delay_counter = 0;

        Log::info('PollMiningObservers: creating jobs to poll ' . count($refineries) . ' refineries');

        // For each refinery create a new job in the queue to poll the API.
        foreach ($refineries as $refinery) {
            PollRefinery::dispatch($refinery->observer_id, $refinery->corporation_id)
                ->delay(Carbon::now()->addSecond(20 * $delay_counter));
            $delay_counter++;
        }

    }

}
