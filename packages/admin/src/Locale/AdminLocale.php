<?php

declare(strict_types=1);

namespace LynxCms\Admin\Locale;

use LynxCms\Core\Models\Setting;
use LynxCms\Core\Support\Value;

/**
 * The language of the admin's own wording (ADR-0097): the editor's choice, then config
 * lynx-admin.locale, then the app's locale. Choices live in Lynx's settings, not on the users
 * table, because apps may keep their users elsewhere (ADR-0033).
 *
 * @api
 */
final class AdminLocale
{
    private const string SETTING_PREFIX = 'admin_locale.';

    /**
     * Languages editors can choose, as code => name in that language.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach ((array) config('lynx-admin.locales', []) as $code => $name) {
            $options[(string) $code] = Value::string($name);
        }

        return $options;
    }

    public static function default(): string
    {
        return Value::string(config('lynx-admin.locale')) ?: Value::string(config('app.locale'), 'en');
    }

    /** A saved choice the site stopped offering counts as no choice. */
    public static function forUser(int|string|null $userId): string
    {
        $saved = $userId === null ? null : self::saved($userId);

        return $saved !== null && isset(self::options()[$saved]) ? $saved : self::default();
    }

    public static function setting(int|string $userId): string
    {
        return self::SETTING_PREFIX.$userId;
    }

    private static function saved(int|string $userId): ?string
    {
        return Setting::read(self::setting($userId));
    }
}
