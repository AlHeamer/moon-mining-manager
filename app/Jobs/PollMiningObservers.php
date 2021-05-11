<?php

namespace App\Jobs;

use App\Models\Refinery;
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
     * @var int
     */
    private $corporationId;

    public function __construct(int $corporationId)
    {
        $this->corporationId = $corporationId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Grab all of the refineries and loop through them.
        $refineries = Refinery::where('corporation_id', $this->corporationId)->where('available', 1)->get();
        $delay_counter = 0;
        // Grab only refineries which are not rented and loop through them.
        /*
           //BNI Change, no longer required? - don't poll mining observers that are rented
           $refineries = Refinery::where('corporation_id', $this->corporationId)
            ->whereNotIn('observer_id', function($query) {
                $query->select('refinery_id')
                    ->from('renters')
                    ->whereNull('end_date')
                    ->get();
        })
        ->get();
        */

        Log::info('PollMiningObservers: creating jobs to poll ' . count($refineries) . ' refineries');

        // For each refinery create a new job in the queue to poll the API.
        foreach ($refineries as $refinery) {
            PollRefinery::dispatch($refinery->observer_id, $refinery->corporation_id)
                ->delay(Carbon::now()->addSecond(20 * $delay_counter));
            $delay_counter++;
        }
    }
}
