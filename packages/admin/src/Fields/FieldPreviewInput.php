<?php

declare(strict_types=1);

namespace LynxCms\Admin\Fields;

use LynxCms\Admin\Editor\FieldInputs;
use LynxCms\Admin\Editor\FieldView;

/**
 * The page editor's own input for the field form's Preview tab, drawn from the settings the form
 * holds now: a rich text field shows the rich text editor with its tools, a text with a limit
 * counts characters, a date with time asks for the time. Types whose input needs a saved page or
 * other fields (choices, pages, repeaters, files) keep the Preview tab's own drawing.
 *
 * @internal
 */
final readonly class FieldPreviewInput
{
    /** The model the previewed input writes to: FieldForm::$previewValue, never saved. */
    public const string MODEL = 'previewValue';

    private const array TYPES = ['text', 'email', 'url', 'icon', 'textarea', 'integer', 'decimal', 'date', 'time'];

    public function __construct(private FieldInputs $inputs) {}

    /** The input view for the type, or null when the Preview tab draws the type itself. */
    public function view(string $type): ?string
    {
        return in_array($type, self::TYPES, true) ? $this->inputs->for($type) : null;
    }

    /**
     * The field as the input view reads it.
     *
     * @param  array<string, mixed>  $settings  the form's settings, as typed
     */
    public function field(string $name, string $label, string $type, array $settings, ?string $placeholder): FieldView
    {
        return new FieldView(
            name: 'preview-'.($name !== '' ? $name : 'field'),
            label: $label,
            type: $type,
            isTranslatable: false,
            isRequired: false,
            description: null,
            values: [],
            settings: $settings,
            placeholder: $placeholder,
        );
    }
}
