<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E4-T5 (§9 step 23) — the contact form's relay mail. Carries the submitter's own address as
 * replyTo so the recipient can just hit reply, rather than storing the submission anywhere
 * (blueprint §15 PII handling: contact-form submissions are not persisted).
 *
 * Deliberately NOT ShouldQueue: QUEUE_CONNECTION is 'database' but the supervised worker that
 * would actually process that queue doesn't exist until E5-T3 — queuing this mail today would
 * mean it silently never sends until that infrastructure lands. Sent synchronously instead.
 */
class ContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $senderName,
        public string $senderEmail,
        // Not named $subject: Mailable already declares an untyped public $subject of its
        // own (set via Envelope below), and a typed promoted property here would conflict
        // with it.
        public string $messageSubject,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Contact form] ' . $this->messageSubject,
            replyTo: [new Address($this->senderEmail, $this->senderName)],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.contact-message');
    }
}
