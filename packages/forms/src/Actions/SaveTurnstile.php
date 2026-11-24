<?php

declare(strict_types=1);

namespace LynxCms\Forms\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Models\Setting;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Spam\Turnstile;

/**
 * Saves the Cloudflare Turnstile keys for public forms (ADR-0148), the secret encrypted with the
 * app key (an empty secret keeps the saved one), or clears them to turn it off. Superusers only.
 * Keys in .env win over these.
 *
 * @api
 */
final class SaveTurnstile extends Action
{
    public static function name(): string
    {
        return 'forms.save_turnstile';
    }

    public static function description(): string
    {
        return 'Turn on Cloudflare Turnstile on public forms with a site key and a secret key, or turn it off with empty keys.';
    }

    public static function rules(): array
    {
        return [
            'site_key' => ['present', 'nullable', 'string', 'max:255'],
            'secret' => ['present', 'nullable', 'string', 'max:255'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'site_key' => $schema->string()->description('The site key from the Cloudflare dashboard; empty turns Turnstile off.')->required(),
            'secret' => $schema->string()->description('The secret key; stored encrypted. Empty keeps the saved one.')->required(),
        ];
    }

    /** @return bool whether Turnstile is on */
    public function handle(Input $input, ActionContext $context): bool
    {
        $siteKey = trim((string) $input->string('site_key'));
        $isOn = $siteKey !== '';
        Setting::write(Turnstile::SETTING, $isOn ? json_encode(['site_key' => $siteKey, 'secret' => $this->secret($input)], JSON_THROW_ON_ERROR) : null);
        $context->changes->updated('setting', null, $isOn ? 'Turn on Turnstile for forms' : 'Turn off Turnstile for forms', ['site_key' => $siteKey]);

        return $isOn;
    }

    /** The new secret, encrypted, or the saved one when none is given. */
    private function secret(Input $input): string
    {
        $secret = trim((string) $input->string('secret'));

        if ($secret !== '') {
            return Crypt::encryptString($secret);
        }

        $saved = Value::string(Value::map(json_decode((string) Setting::read(Turnstile::SETTING), true))['secret'] ?? '');

        return $saved !== '' ? $saved : throw ValidationException::withMessages(['secret' => Value::string(__('lynx-forms::validation.turnstile_secret'))]);
    }
}
