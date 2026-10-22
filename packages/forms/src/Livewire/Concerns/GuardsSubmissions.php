<?php

declare(strict_types=1);

namespace LynxCms\Forms\Livewire\Concerns;

use Illuminate\Support\Facades\RateLimiter;
use LynxCms\Core\Support\Value;
use Spatie\Honeypot\Events\SpamDetectedEvent;
use Spatie\Honeypot\Exceptions\SpamException;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\SpamProtection;

/**
 * Spam protection for public forms (ADR-0052): Spatie's honeypot and timing check, and Laravel's
 * rate limiter per form and visitor (a hash of the IP, never stored).
 */
trait GuardsSubmissions
{
    public HoneypotData $extraFields;

    public function mountGuardsSubmissions(): void
    {
        $this->extraFields = new HoneypotData;
    }

    /** Seconds until the visitor may send again, or null when they may now. */
    protected function secondsUntilAllowed(): ?int
    {
        $key = $this->rateLimitKey();

        return RateLimiter::tooManyAttempts($key, Value::int(config('lynx-forms.rate_limit.attempts'), 5))
            ? RateLimiter::availableIn($key)
            : null;
    }

    protected function countAttempt(): void
    {
        RateLimiter::hit($this->rateLimitKey(), Value::int(config('lynx-forms.rate_limit.decay_seconds'), 600));
    }

    /** Spatie's check, without its 403: a caught bot sees the usual thanks and learns nothing. */
    protected function looksLikeSpam(): bool
    {
        try {
            app(SpamProtection::class)->check($this->extraFields->toArray());

            return false;
        } catch (SpamException) {
            event(new SpamDetectedEvent(request()));

            return true;
        }
    }

    private function rateLimitKey(): string
    {
        return 'lynx-forms:'.$this->form.':'.hash('sha256', (string) request()->ip());
    }
}
