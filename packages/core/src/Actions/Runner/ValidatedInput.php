<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Runner;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Support\Value;

/**
 * An action's input after its rules: unknown keys refused when the action asks for it
 * (RefusesUnknownInput), then validated. Validated values win, but recursively: rules for some
 * nested keys ("settings.description") must not drop their siblings ("settings.format") that the
 * action validates itself.
 *
 * @internal
 */
final class ValidatedInput
{
    /**
     * @param  class-string<Action>  $action
     * @param  array<string, mixed>|Input  $input
     *
     * @throws ValidationException
     */
    public static function for(string $action, array|Input $input): Input
    {
        $data = $input instanceof Input ? $input->all() : $input;

        if (is_subclass_of($action, RefusesUnknownInput::class)) {
            UnknownInput::refuse($data, $action::rules());
        }

        return new Input(Value::map(array_replace_recursive($data, Validator::make($data, $action::rules())->validate())));
    }
}
