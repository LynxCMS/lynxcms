<?php

declare(strict_types=1);

namespace LynxCms\Forms\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\FormSchema;

/**
 * A form's name, which is its key (ADR-0053): lowercase letters, digits and single dashes,
 * starting with a letter, as "contact-us". The message says so, where a bare regex rule says
 * only that the format is wrong.
 *
 * @api
 */
final readonly class FormName implements ValidationRule
{
    public const int MAX_LENGTH = 64;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_strlen($value) > self::MAX_LENGTH || preg_match(FormSchema::NAME_PATTERN, $value) !== 1) {
            $fail(Value::string(__('lynx-forms::validation.form.name', ['max' => self::MAX_LENGTH])));
        }
    }
}
