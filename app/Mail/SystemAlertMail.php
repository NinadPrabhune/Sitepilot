<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SystemAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public $alertData;

    public function __construct(array $alertData)
    {
        $this->alertData = $alertData;
    }

    public function build()
    {
        return $this->subject('🚨 CRITICAL: Financial System Alert')
            ->view('emails.system-alert')
            ->with(['data' => $this->alertData]);
    }
}
