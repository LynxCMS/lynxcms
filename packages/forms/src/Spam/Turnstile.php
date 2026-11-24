<?php

declare(strict_types=1);

namespace LynxCms\Forms\Spam;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LynxCms\Core\Models\Setting;
use LynxCms\Core\Support\Value;

/**
 * Cloudflare Turnstile on public forms (ADR-0148): on when a site key and a secret key are set,
 * in .env (LYNX_FORMS_TURNSTILE_SITE_KEY, LYNX_FORMS_TURNSTILE_SECRET), or else by a superuser in
 * Forms > Forms, where the secret is stored encrypted with the app key. A form then shows the
 * widget, and a submission is sent only once Cloudflare confirms its token. A check that can't
 * reach Cloudflare fails, so bots don't get through while it is down.
 *
 * @internal
 */
final readonly class Turnstile
{
    public const string SETTING = 'forms.turnstile';

    public const string SCRIPT = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=lynxTurnstileReady';

    private const string VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private const int TIMEOUT_SECONDS = 5;

    public function isEnabled(): bool
    {
        return $this->siteKey() !== '' && $this->secret() !== '';
    }

    public function siteKey(): string
    {
        return Value::string(config('lynx-forms.turnstile.site_key')) ?: Value::string($this->stored()['site_key'] ?? '');
    }

    /** Whether .env sets the keys, so the admin's fields only show them. */
    public function isFromEnv(): bool
    {
        return Value::string(config('lynx-forms.turnstile.site_key')) !== '';
    }

    public function verify(string $token, ?string $ip): bool
    {
        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(self::TIMEOUT_SECONDS)->post(self::VERIFY_URL, array_filter(['secret' => $this->secret(), 'response' => $token, 'remoteip' => $ip]));
        } catch (ConnectionException $e) {
            Log::warning('lynx-forms: Turnstile could not be reached; the submission was refused.', ['error' => $e->getMessage()]);

            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }

    private function secret(): string
    {
        $fromEnv = Value::string(config('lynx-forms.turnstile.secret'));

        if ($fromEnv !== '') {
            return $fromEnv;
        }

        try {
            $encrypted = Value::string($this->stored()['secret'] ?? '');

            return $encrypted === '' ? '' : Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            // A changed app key: the stored secret is lost, and the widget stays off until it is saved again.
            return '';
        }
    }

    /** @return array<array-key, mixed> */
    private function stored(): array
    {
        return Value::map(json_decode((string) Setting::read(self::SETTING), true));
    }
}
