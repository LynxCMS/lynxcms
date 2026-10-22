<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates;

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Support\Value;

/**
 * The page editor settings of a template's fields (ADR-0063): the widths a field may take, and
 * the checks on editor, width and icon.
 *
 * @api
 */
final class EditorSettings
{
    public const array WIDTHS = [25, 33, 50, 66, 75, 100];

    public const int FULL_WIDTH = 100;

    private const string ICON_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * @param  array<string, mixed>  $settings  the template field's settings
     */
    public static function assert(string $name, array $settings): void
    {
        $editor = isset($settings['editor']) ? FieldDisplay::tryFrom(Value::string($settings['editor'])) : FieldDisplay::Open;

        $problem = match (true) {
            $editor === null => 'editor is one of '.implode(', ', array_column(FieldDisplay::cases(), 'value')).'.',
            isset($settings['width']) && ! in_array(Value::int($settings['width']), self::WIDTHS, true) => 'width is one of '.implode(', ', self::WIDTHS).'.',
            isset($settings['icon']) && preg_match(self::ICON_PATTERN, Value::string($settings['icon'])) !== 1 => 'icon is a Lucide icon name, such as "calendar".',
            ! $editor->isEditable() && (($settings['required'] ?? false) === true || trim(Value::string($settings['required_if'] ?? '')) !== '') => 'a '.$editor->value.' field cannot be required: editors could not fill it.',
            default => null,
        };

        if ($problem !== null) {
            throw ValidationException::withMessages(['fields' => "Field [{$name}]: {$problem}"]);
        }
    }
}
