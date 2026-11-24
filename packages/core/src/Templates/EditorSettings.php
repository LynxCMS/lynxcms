<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates;

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Support\Value;

/**
 * The page editor settings of a template's fields (ADR-0063): the widths a field may take, and
 * the checks on editor, width and icon.
 *
 * @internal
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
        $isLocked = ($settings['locked'] ?? false) === true || $editor === FieldDisplay::Locked || lynx()->fields->find($name)?->isLocked() === true;
        $isLazy = ($settings['lazy'] ?? false) === true;

        $problem = match (true) {
            $editor === null => Value::string(__('lynx::structure.editor.display', ['values' => implode(', ', array_column(FieldDisplay::cases(), 'value'))])),
            isset($settings['locked']) && ! is_bool($settings['locked']) => Value::string(__('lynx::structure.editor.boolean', ['setting' => 'locked'])),
            isset($settings['lazy']) && ! is_bool($settings['lazy']) => Value::string(__('lynx::structure.editor.boolean', ['setting' => 'lazy'])),
            // A tab's fields render only once the tab is opened already, so lazy is for fields that close.
            $isLazy && ! $editor->isCollapsible() => Value::string(__('lynx::structure.editor.lazy')),
            $isLocked && $editor === FieldDisplay::Hidden => Value::string(__('lynx::structure.editor.locked_hidden')),
            isset($settings['width']) && ! in_array(Value::int($settings['width']), self::WIDTHS, true) => Value::string(__('lynx::structure.editor.width', ['values' => implode(', ', self::WIDTHS)])),
            isset($settings['icon']) && preg_match(self::ICON_PATTERN, Value::string($settings['icon'])) !== 1 => Value::string(__('lynx::structure.editor.icon')),
            ($isLocked || ! $editor->isEditable()) && (($settings['required'] ?? false) === true || trim(Value::string($settings['required_if'] ?? '')) !== '') => Value::string(__('lynx::structure.editor.required', ['display' => $isLocked ? 'locked' : $editor->value])),
            default => null,
        };

        if ($problem !== null) {
            throw ValidationException::withMessages(['fields' => Value::string(__('lynx::structure.field.problem', ['field' => $name, 'problem' => $problem]))]);
        }
    }
}
