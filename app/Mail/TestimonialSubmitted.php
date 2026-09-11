<?php

namespace App\Mail;

use App\Models\Testimonial;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E6-T2 (§9 step 45) — optional admin notification when a visitor submits an endorsement.
 * Dispatched to config('services.contact.to_address') only when that's set; a delivery
 * failure never loses the Testimonial row itself (TestimonialForm::notifyAdmin() swallows
 * it) — this is a courtesy notice, not the record of the submission.
 */
class TestimonialSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Testimonial $testimonial,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New endorsement submitted — pending review',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.testimonial-submitted');
    }
}
