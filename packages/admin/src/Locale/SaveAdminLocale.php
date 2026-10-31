<?php

declare(strict_types=1);

namespace LynxCms\Admin\Locale;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Models\Setting;

/**
 * Saves the language a signed-in editor reads the admin in (ADR-0097). Empty goes back to the
 * admin's default. Personal, so it is not an agent tool and stays out of the activity log.
 *
 * @api
 */
final class SaveAdminLocale extends Action
{
    public static function name(): string
    {
        return 'admin.set_locale';
    }

    public static function description(): string
    {
        return 'Choose the language the signed-in editor reads the admin in. Send it empty to go back to the default.';
    }

    public static function permission(): string
    {
        return 'admin.access';
    }

    public static function exposedAsTool(): bool
    {
        return false;
    }

    public static function logsActivity(): bool
    {
        return false;
    }

    public static function rules(): array
    {
        return ['locale' => ['present', 'nullable', 'string', 'max:32']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['locale' => $schema->string()->description('A code from config lynx-admin.locales. Empty: the default.')->required()];
    }

    public function handle(Input $input, ActionContext $context): string
    {
        if ($context->actor->kind !== Actor::USER || $context->actor->id === null) {
            throw ValidationException::withMessages(['locale' => 'Only a signed-in user has an admin language.']);
        }

        $locale = (string) $input->string('locale', '');

        if ($locale !== '' && ! isset(AdminLocale::options()[$locale])) {
            throw ValidationException::withMessages(['locale' => "The admin does not offer the language [{$locale}]."]);
        }

        $setting = AdminLocale::setting($context->actor->id);
        Setting::write($setting, $locale === '' ? null : $locale);
        $context->changes->updated('setting', $setting, 'Change the admin language', ['locale' => $locale]);

        return AdminLocale::forUser($context->actor->id);
    }
}
