<?php

declare(strict_types=1);

namespace LynxCms\Admin\Templates;

use LynxCms\Admin\Editor\LocalizedForm;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\EditorSettings;
use LynxCms\Core\Templates\FieldDisplay;

/**
 * One field's row on Setup > Templates > Edit template (TemplateForm): the template's overrides
 * for the field (label, help, conditions, flags, how it shows in the editor) as the form holds
 * them, and back as the settings templates.update stores.
 *
 * @internal
 */
final class TemplateFieldRow
{
    private const array OVERRIDES = ['show_if', 'required_if', 'icon'];

    /** Overrides editors read, per language (ADR-0043). */
    private const array LOCALIZED_OVERRIDES = ['label', 'description', 'notes', 'placeholder'];

    /** Yes or no overrides, stored only when set. */
    private const array FLAGS = ['required', 'searchable'];

    /**
     * @param  array<string, mixed>  $settings
     * @return array{label: array<string, string>, description: array<string, string>, notes: array<string, string>, placeholder: array<string, string>, required: bool, searchable: bool, show_if: string, required_if: string, icon: string, editor: string, width: string}
     */
    public static function toForm(array $settings): array
    {
        return [
            'label' => LocalizedForm::toForm($settings['label'] ?? null),
            'description' => LocalizedForm::toForm($settings['description'] ?? null),
            'notes' => LocalizedForm::toForm($settings['notes'] ?? null),
            'placeholder' => LocalizedForm::toForm($settings['placeholder'] ?? null),
            'required' => (bool) ($settings['required'] ?? false),
            'searchable' => (bool) ($settings['searchable'] ?? false),
            'show_if' => Value::string($settings['show_if'] ?? ''),
            'required_if' => Value::string($settings['required_if'] ?? ''),
            'icon' => Value::string($settings['icon'] ?? ''),
            // The older editor: locked is open and locked (ADR-0149).
            'editor' => Value::string($settings['editor'] ?? '') === FieldDisplay::Locked->value ? FieldDisplay::Open->value : Value::string($settings['editor'] ?? FieldDisplay::Open->value),
            'locked' => ($settings['locked'] ?? false) === true || Value::string($settings['editor'] ?? '') === FieldDisplay::Locked->value,
            'lazy' => ($settings['lazy'] ?? false) === true,
            'width' => (string) Value::int($settings['width'] ?? EditorSettings::FULL_WIDTH),
        ];
    }

    /**
     * The overrides set in a row; empty ones are left out so the field's own values apply.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function fromForm(array $row): array
    {
        $settings = [];

        foreach (self::OVERRIDES as $key) {
            $value = trim(Value::string($row[$key] ?? ''));

            if ($value !== '') {
                $settings[$key] = $value;
            }
        }

        foreach (self::LOCALIZED_OVERRIDES as $key) {
            $value = LocalizedForm::fromForm(Value::array($row[$key] ?? []));

            if ($value !== null) {
                $settings[$key] = $value;
            }
        }

        foreach (self::FLAGS as $flag) {
            if ((bool) ($row[$flag] ?? false)) {
                $settings[$flag] = true;
            }
        }

        return $settings + self::editorSettingsOf($row);
    }

    /**
     * How the field shows in the page editor (ADR-0063), whether it is locked and loads when
     * opened (ADR-0149), left out at the defaults.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function editorSettingsOf(array $row): array
    {
        $editor = Value::string($row['editor'] ?? '');
        $width = Value::int($row['width'] ?? EditorSettings::FULL_WIDTH);

        return array_filter([
            'editor' => in_array($editor, ['', FieldDisplay::Open->value], true) ? null : $editor,
            'locked' => ($row['locked'] ?? false) === true ? true : null,
            'lazy' => ($row['lazy'] ?? false) === true ? true : null,
            'width' => $width === EditorSettings::FULL_WIDTH ? null : $width,
        ], fn (mixed $value): bool => $value !== null);
    }
}
