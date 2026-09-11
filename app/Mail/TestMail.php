<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E5-T1 (§9 step 35) — dispatched by the "Mail settings" Filament Page's "Send test email"
 * header action, to the configured from-address, against whatever SMTP config
 * ConfiguresMailFromSettings currently has live. Sent synchronously (same reasoning as
 * ContactMessageMail) — the action needs the real send/fail result immediately to flash it.
 */
class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'miautrix — test email',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.test-mail');
    }
}
