<?php

namespace App\Jobs;

use App\Classes\EsiConnection;
use App\Refinery;
use App\Renter;
use App\Template;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateRentReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 10;
    private $id;
    private $mail_delay;

    /**
     * Create a new job instance.
     *
     * @param int $id
     * @return void
     */
    public function __construct($id, $mail_delay = 20)
    {
        $this->id = $id;
        $this->mail_delay = $mail_delay;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        // Retrieve the renter record.
        $renter = Renter::find($this->id);

        // Request the character name for this rental agreement.
        $esi = new EsiConnection;
        $character = $esi->getConnection()->invoke('get', '/characters/{character_id}/', [
            'character_id' => $renter->character_id,
        ]);

        // Grab a reference to the refinery that is being rented.
        $refinery = Refinery::where('observer_id', $renter->refinery_id)->first();

        // Grab the current outstanding balance for this refinery.
        $outstanding_balance = round($renter->amount_owed);

        // Pick up the renter reminder template to apply text substitutions.
        $template = Template::where('name', 'renter_reminder')->first();

        // Grab the template subject and body.
        $subject = $template->subject;
        $body = $template->body;

        // Replace placeholder elements in email template.
        $subject = str_replace('{date}', date('Y-m-d'), $subject);
        $subject = str_replace('{name}', $character->name, $subject);
        $subject = str_replace('{outstanding_balance}', number_format($outstanding_balance, 0), $subject);
        $body = str_replace('{date}', date('Y-m-d'), $body);
        $body = str_replace('{name}', $character->name, $body);
        $body = str_replace('{refinery}', $refinery->name, $body);
        $body = str_replace('{outstanding_balance}', number_format($outstanding_balance, 0), $body);
        $mail = array(
            'body' => $body,
            'recipients' => array(
                array(
                    'recipient_id' => $renter->character_id,
                    'recipient_type' => 'character'
                )
            ),
            'subject' => $subject,
            'approved_cost' => 5000,
        );

        // Queue sending the evemail, spaced at 1 minute intervals to avoid triggering the mailspam limiter (4/min).
        SendEvemail::dispatch($mail)->delay(Carbon::now()->addMinutes($this->mail_delay));
        Log::info('GenerateRentReminder: dispatched job to send mail in ' . $this->mail_delay . ' minutes', [
            'mail' => $mail,
        ]);
    }
}
