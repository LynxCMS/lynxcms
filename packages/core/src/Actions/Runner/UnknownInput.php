<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Runner;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Support\Value;

/**
 * Refuses input keys an action's rules() don't name (RefusesUnknownInput), and names the
 * snake_case key when the caller wrote a known one in camelCase.
 *
 * @internal
 */
final class UnknownInput
{
    /**
     * @param  array<array-key, mixed>  $input
     * @param  array<string, mixed>  $rules
     *
     * @throws ValidationException
     */
    public static function refuse(array $input, array $rules): void
    {
        $known = array_unique(array_map(fn (string $rule): string => Str::before($rule, '.'), array_keys($rules)));
        $unknown = array_diff(array_map(strval(...), array_keys($input)), $known);

        if ($unknown === []) {
            return;
        }

        throw ValidationException::withMessages(array_map(
            fn (string $key): string => self::message($key, $known),
            array_combine($unknown, $unknown),
        ));
    }

    /** @param  list<string>|array<int, string>  $known */
    private static function message(string $key, array $known): string
    {
        $snake = Str::snake($key);

        return Value::string(in_array($snake, $known, true)
            ? __('lynx::validation.input.unknown_snake', ['key' => $key, 'suggestion' => $snake])
            : __('lynx::validation.input.unknown', ['key' => $key, 'known' => implode(', ', $known)]));
    }
}
