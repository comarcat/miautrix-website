<?php

namespace App\Livewire;

use App\Mail\TestimonialSubmitted;
use App\Models\Testimonial;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Throwable;

/**
 * Phase 2 (E6-T2, §9 step 45) — the public endorsement submission form (backlog item 15,
 * `/endorsements`, professional area only — never mounted under `/life`). Honeypot + rate
 * limit are copied from ContactForm, not imported (§9's own instruction) — same field name,
 * same CSS-hidden technique, same RateLimiter::for-style 5/hour/IP guard, applied here
 * directly because Livewire component actions all share one endpoint and there is no
 * per-feature route to attach `throttle:` to.
 *
 * Always writes `status='pending'` — the public form has no way to set any other status;
 * an admin approves or rejects it in Filament (E6-T4).
 */
class TestimonialForm extends Component
{
    public string $name = '';

    public string $organization = '';

    public string $role = '';

    public string $contact_type = Testimonial::CONTACT_TYPE_EMAIL;

    public string $contact_value = '';

    public string $body = '';

    /**
     * Honeypot — same field name and CSS-hidden (sr-only, not display:none) technique as
     * ContactForm's own, so both forms look identical to a scraper filling every field in
     * the DOM.
     */
    public string $website_url_confirm = '';

    public bool $submitted = false;

    public ?string $rateLimitMessage = null;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'contact_type' => ['required', 'in:' . implode(',', [
                Testimonial::CONTACT_TYPE_EMAIL,
                Testimonial::CONTACT_TYPE_LINKEDIN,
                Testimonial::CONTACT_TYPE_URL,
            ])],
            'contact_value' => [
                'required',
                'string',
                'max:255',
                $this->contact_type === Testimonial::CONTACT_TYPE_EMAIL ? 'email' : 'url',
            ],
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    public function submit(): void
    {
        $this->rateLimitMessage = null;

        // Honeypot filled: silently discard — a real success response (indistinguishable
        // from a genuine submission) so a bot has no signal it was caught, no row written.
        if ($this->website_url_confirm !== '') {
            $this->resetForm();
            $this->submitted = true;

            return;
        }

        $throttleKey = 'testimonial:' . request()->ip();

        // 5/hour/IP, checked before validation so a rate-limited visitor sees the limiter
        // message rather than being told their (possibly valid) input has errors.
        if (RateLimiter::tooManyAttempts($throttleKey, Limit::perHour(5)->maxAttempts)) {
            $this->rateLimitMessage = 'Too many submissions from your network. Please try again in an hour.';

            return;
        }

        $this->validate();

        RateLimiter::hit($throttleKey, 3600);

        $testimonial = Testimonial::create([
            'name' => $this->name,
            'organization' => $this->organization ?: null,
            'role' => $this->role ?: null,
            'contact_type' => $this->contact_type,
            'contact_value' => $this->contact_value,
            'body' => $this->body,
            'status' => Testimonial::STATUS_PENDING,
        ]);

        $this->notifyAdmin($testimonial);

        $this->resetForm();
        $this->submitted = true;
    }

    /**
     * Optional — a delivery failure here must never lose the testimonial itself, which is
     * already safely written by the time this runs.
     */
    private function notifyAdmin(Testimonial $testimonial): void
    {
        $to = config('services.contact.to_address');

        if (! $to) {
            return;
        }

        try {
            Mail::to($to)->send(new TestimonialSubmitted($testimonial));
        } catch (Throwable) {
            // Swallowed deliberately — see this method's own docblock.
        }
    }

    private function resetForm(): void
    {
        $this->reset(['name', 'organization', 'role', 'contact_value', 'body', 'website_url_confirm']);
        $this->contact_type = Testimonial::CONTACT_TYPE_EMAIL;
    }

    public function render()
    {
        return view('livewire.testimonial-form');
    }
}
