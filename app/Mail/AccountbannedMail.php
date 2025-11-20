<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountbannedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $reviewer;
    public $status;
    public $adminMessage; // instead of $message
    public function __construct($reviewer, $status,$adminMessage = nul)
    {
        $this->reviewer=$reviewer;
        $this->status=$status;
       
        $this->adminMessage = $adminMessage;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Turekonnect Account Status Update',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'Mail.bannedaccount', // Blade file
            with: [
                'reviewer' => $this->reviewer,
                'status'   => $this->status,
                'adminMessage' => $this->adminMessage, // renamed

            ]
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
}
