<?php

namespace App\Mail;

use App\Models\FeedbackInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeedbackInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public FeedbackInvitation $invitation,
        public string $token
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Share Your Feedback'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.feedback-invitation',
        );
    }
}
