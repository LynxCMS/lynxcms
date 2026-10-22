<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin;

use LynxCms\Admin\Editor\ChoicesForm;
use LynxCms\Admin\Editor\FormConverter;
use LynxCms\Core\Models\Field;

/**
 * Options in the page editor: each row with its value and a label for every language, so the
 * list editor shows one input per language. Saving keeps the rows; OptionsType cleans them.
 *
 * @internal
 */
final readonly class OptionsConverter implements FormConverter
{
    public function toForm(mixed $value, ?Field $field = null): mixed
    {
        return ChoicesForm::toForm($value);
    }

    public function fromForm(mixed $value, ?Field $field = null): mixed
    {
        return ChoicesForm::fromForm($value);
    }
}
