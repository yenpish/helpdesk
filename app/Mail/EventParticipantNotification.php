<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventParticipantNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Event $event, public string $type)
    {
    }

    public function build(): self
    {
        $labels = [
            'published' => 'Event published',
            'updated' => 'Event updated',
            'cancelled' => 'Event cancelled',
            'reminder_24h' => 'Event reminder: 24 hours',
            'reminder_1h' => 'Event reminder: 1 hour',
        ];

        return $this->subject(($labels[$this->type] ?? 'Event notice') . ': ' . $this->event->name)
            ->view('emails.event-participant-notification');
    }
}
