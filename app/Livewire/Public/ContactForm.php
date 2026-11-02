<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Jobs\SendEnquiryEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ContactForm extends Component
{
    public const int MAX_PER_HOUR = 5;

    #[Locked]
    public int $organizationId;

    public string $name = '';

    public string $email = '';

    public string $subject = '';

    public string $message = '';

    /** Honeypot: real visitors never see or fill this field. */
    public string $website = '';

    public bool $sent = false;

    public function submit(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        // Bots that fill the hidden field get the same success state, so they learn nothing.
        if ($this->website !== '') {
            $this->sent = true;

            return;
        }

        $limiterKey = 'enquiry:' . (request()->ip() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_PER_HOUR)) {
            throw ValidationException::withMessages([
                'message' => 'Too many messages sent. Please try again later.',
            ]);
        }

        RateLimiter::hit($limiterKey, 3600);

        SendEnquiryEmail::dispatch($this->organizationId, [
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'subject' => (string) $validated['subject'],
            'message' => (string) $validated['message'],
        ]);

        $this->reset(['name', 'email', 'subject', 'message']);
        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.public.contact-form');
    }
}
