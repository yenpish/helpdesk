<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegistrationDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Event $event,
        public Registration $registration,
        public string $decision
    ) {}

    public function build(): self
    {
        $approved = $this->decision === 'approved';

        return $this
            ->subject(($approved ? 'Pre-registration approved: ' : 'Pre-registration not approved: ').$this->event->name)
            ->view('emails.registration-decision', ['approved' => $approved]);
    }
}
