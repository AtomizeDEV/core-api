<?php

namespace Fleetbase\Mail;

use Fleetbase\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * The test mail subject.
     */
    public string $mailSubject = 'Your Fastlane Mail Configuration Works!';

    /**
     * The user the email is to.
     */
    public User $user;

    /**
     * Creates an instance of the TestMail.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get the message content definition.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address($this->user->email, $this->user->name)],
            subject: $this->mailSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'fleetbase::mail.test',
            with: [
                'user'        => $this->user,
                'currentHour' => now()->hour,
            ]
        );
    }
}
