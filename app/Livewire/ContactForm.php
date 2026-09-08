<?php

namespace App\Livewire;

use App\Mail\ContactMessageMail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * E4-T5 (§9 step 23) — the one genuinely interactive public surface (blueprint §6). Honeypot
 * + server-side rate limit, both enforced here rather than via route middleware: Livewire
 * component actions all share one endpoint (/livewire/update), so there is no per-feature
 * route to attach `throttle:` to — the limiter is applied manually instead.
 */
class ContactForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $subject = '';

    public string $message = '';

    /**
     * Honeypot (blueprint §9 step 23): hidden via CSS (Tailwind's `sr-only`, which clips
     * rather than `display:none`s) so a real screen-reader user never encounters it, but a
     * basic scraper that fills every field in the DOM still trips it.
     */
    public string $website_url_confirm = '';

    public bool $submitted = false;

    public ?string $rateLimitMessage = null;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }

    public function submit(): void
    {
        $this->rateLimitMessage = null;

        // Honeypot filled: silently discard. A real 200/success response (indistinguishable
        // from a genuine submission) so a bot has no signal it was caught, no mail sent, no
        // row written (there is no row to write — submissions were never persisted).
        if ($this->website_url_confirm !== '') {
            $this->resetForm();
            $this->submitted = true;

            return;
        }

        $throttleKey = 'contact:' . request()->ip();

        // 5/hour/IP (blueprint §9 step 23 / §15). Checked before validation so a rate-limited
        // visitor sees the limiter message rather than being told their (possibly perfectly
        // valid) input has validation errors.
        if (RateLimiter::tooManyAttempts($throttleKey, Limit::perHour(5)->maxAttempts)) {
            $this->rateLimitMessage = 'Too many submissions from your network. Please try again in an hour.';

            return;
        }

        $this->validate();

        RateLimiter::hit($throttleKey, 3600);

        Mail::to(config('services.contact.to_address'))->send(new ContactMessageMail(
            senderName: $this->name,
            senderEmail: $this->email,
            messageSubject: $this->subject,
            body: $this->message,
        ));

        $this->resetForm();
        $this->submitted = true;
    }

    private function resetForm(): void
    {
        $this->reset(['name', 'email', 'subject', 'message', 'website_url_confirm']);
    }

    public function render()
    {
        return view('livewire.contact-form');
    }
}
