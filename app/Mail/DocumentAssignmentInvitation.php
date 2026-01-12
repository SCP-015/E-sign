<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentAssignmentInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public $document;
    public $inviteToken;
    public $senderName;

    /**
     * Create a new message instance.
     */
    public function __construct($document, $inviteToken, $senderName)
    {
        $this->document = $document;
        $this->inviteToken = $inviteToken;
        $this->senderName = $senderName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been assigned a document to sign: {$this->document->title}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.documents.assignment_invitation',
            with: [
                'url' => config('app.url') . '/?email=' . urlencode($this->document->signers->where('invite_token', $this->inviteToken)->first()->email) . '&token=' . $this->inviteToken,
                'documentTitle' => $this->document->title,
                'senderName' => $this->senderName,
            ],
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
