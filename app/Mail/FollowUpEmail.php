<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FollowUpEmail extends Mailable
{
    use Queueable, SerializesModels;
    public $applicantName;

    /**
     * Create a new message instance.
     */
    public function __construct($applicantName)
    {
        $this->applicantName = $applicantName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Follow Up Email',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    public function build()
    {
        return $this->subject('Your Application is Under Review')
                    ->view('emails.followup')
                    ->with([
                        'name' => $this->applicantName,
                    ]);
    }
}
