<?php

declare(strict_types=1);

namespace LynxCms\PagePassword\Admin;

use LynxCms\Admin\Editor\FormConverter;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;
use LynxCms\PagePassword\StoredPassword;

/**
 * The page editor shows the password itself, so editors can read it to send it to guests, and
 * sends what they type; PasswordType encrypts it on save.
 *
 * @internal
 */
final readonly class PasswordConverter implements FormConverter
{
    public function toForm(mixed $value, ?Field $field = null): mixed
    {
        return new StoredPassword(is_string($value) ? $value : null)->reveal() ?? '';
    }

    public function fromForm(mixed $value, ?Field $field = null): mixed
    {
        $password = trim(Value::string($value));

        return $password === '' ? null : $password;
    }
}
