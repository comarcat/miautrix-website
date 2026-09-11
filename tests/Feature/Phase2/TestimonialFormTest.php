<?php

namespace Tests\Feature\Phase2;

use App\Livewire\TestimonialForm;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * E6-T2 (Phase 2, p2-step-45) — the public endorsement form: a valid submission writes one
 * pending Testimonial, the honeypot silently discards, a 6th submission/hour/IP is
 * rate-limited, and contact_value is validated against the chosen contact_type.
 */
class TestimonialFormTest extends TestCase
{
    use RefreshDatabase;

    private function fillValid()
    {
        return Livewire::test(TestimonialForm::class)
            ->set('name', 'Ada Lovelace')
            ->set('organization', 'Acme Corp')
            ->set('role', 'Engineering Director')
            ->set('contact_type', 'email')
            ->set('contact_value', 'ada@example.test')
            ->set('body', 'Fantastic to work with.');
    }

    public function test_a_valid_submission_creates_one_pending_testimonial_and_shows_the_message(): void
    {
        Mail::fake();

        $this->fillValid()
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $this->assertSame(1, Testimonial::count());
        $this->assertSame('pending', Testimonial::first()->status);
    }

    public function test_the_honeypot_silently_discards_the_submission_with_no_row_written(): void
    {
        Mail::fake();

        $this->fillValid()
            ->set('website_url_confirm', 'http://spam.example')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $this->assertSame(0, Testimonial::count());
        Mail::assertNothingSent();
    }

    public function test_the_sixth_submission_from_the_same_ip_within_an_hour_is_rate_limited(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Livewire::test(TestimonialForm::class)
                ->set('name', "Ada {$i}")
                ->set('contact_type', 'email')
                ->set('contact_value', "ada{$i}@example.test")
                ->set('body', 'Great work.')
                ->call('submit')
                ->assertHasNoErrors()
                ->assertSet('rateLimitMessage', null);
        }

        $this->assertSame(5, Testimonial::count());

        Livewire::test(TestimonialForm::class)
            ->set('name', 'Ada 6')
            ->set('contact_type', 'email')
            ->set('contact_value', 'ada6@example.test')
            ->set('body', 'Great work.')
            ->call('submit')
            ->assertSet('rateLimitMessage', fn ($message) => is_string($message) && $message !== '');

        $this->assertSame(5, Testimonial::count());
    }

    public function test_contact_value_is_validated_as_an_email_when_contact_type_is_email(): void
    {
        Livewire::test(TestimonialForm::class)
            ->set('name', 'Ada Lovelace')
            ->set('contact_type', 'email')
            ->set('contact_value', 'not-an-email')
            ->set('body', 'Great work.')
            ->call('submit')
            ->assertHasErrors(['contact_value' => 'email']);

        $this->assertSame(0, Testimonial::count());
    }

    public function test_contact_value_is_validated_as_a_url_for_linkedin_and_url(): void
    {
        Livewire::test(TestimonialForm::class)
            ->set('name', 'Ada Lovelace')
            ->set('contact_type', 'linkedin')
            ->set('contact_value', 'not a url')
            ->set('body', 'Great work.')
            ->call('submit')
            ->assertHasErrors(['contact_value' => 'url']);

        Livewire::test(TestimonialForm::class)
            ->set('name', 'Ada Lovelace')
            ->set('contact_type', 'linkedin')
            ->set('contact_value', 'https://www.linkedin.com/in/ada')
            ->set('body', 'Great work.')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(1, Testimonial::count());
    }
}
