<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Validation\Rules\Password;
use LynxCms\Core\Support\Value;

/**
 * The app's password rules (Password::defaults()) as a checklist for the password forms. Each
 * rule Laravel can test in the browser carries its pattern, the same one Laravel's rule uses;
 * "uncompromised" is checked only when the form is sent.
 *
 * @internal
 */
final class PasswordRules
{
    /** Laravel's own patterns (Illuminate\Validation\Rules\Password), written for JavaScript's "u" flag. */
    private const array PATTERNS = [
        'letters' => '\p{L}',
        'mixedCase' => '(\p{Ll}+.*\p{Lu})|(\p{Lu}+.*\p{Ll})',
        'numbers' => '\p{N}',
        'symbols' => '\p{Z}|\p{S}|\p{P}',
    ];

    /** @return list<array{key: string, label: string, min?: int, max?: int, pattern?: string}> */
    public function all(): array
    {
        $applied = Password::default()->appliedRules();
        $rules = [['key' => 'min', 'label' => __('lynx-admin::auth.password_rules.min', ['min' => Value::int($applied['min'])]), 'min' => Value::int($applied['min'])]];

        if (Value::int($applied['max']) > 0) {
            $rules[] = ['key' => 'max', 'label' => __('lynx-admin::auth.password_rules.max', ['max' => Value::int($applied['max'])]), 'max' => Value::int($applied['max'])];
        }

        foreach (self::PATTERNS as $key => $pattern) {
            if ($applied[$key] === true && ($key !== 'letters' || $applied['mixedCase'] !== true)) {
                $rules[] = ['key' => $key, 'label' => __('lynx-admin::auth.password_rules.'.$key), 'pattern' => $pattern];
            }
        }

        if ($applied['uncompromised'] === true) {
            $rules[] = ['key' => 'uncompromised', 'label' => __('lynx-admin::auth.password_rules.uncompromised')];
        }

        return $rules;
    }
}
