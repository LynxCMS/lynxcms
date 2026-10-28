<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates;

use LynxCms\Core\Fields\UseSettings;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;

/**
 * A field as used by one template: order, required flag, label override and conditions.
 *
 * @api
 */
final readonly class TemplateField
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public Field $field,
        public int $sort,
        public array $settings = [],
    ) {}

    /**
     * The field's settings on this template: its own, with the ones the template sets for it,
     * such as a page field's "max" (ADR-0094).
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return UseSettings::of($this->field, $this->settings);
    }

    public function name(): string
    {
        return $this->field->name;
    }

    /** The template's label for the field, else the field's, in the current language (ADR-0043). */
    public function label(): string
    {
        return $this->labels()->in();
    }

    public function labels(): LocalizedText
    {
        $own = LocalizedText::from($this->settings['label'] ?? null);

        return $own->isEmpty() ? $this->field->labels() : $own;
    }

    public function description(): string
    {
        $own = LocalizedText::from($this->settings['description'] ?? null);

        return $own->isEmpty() ? $this->field->description() : $own->in();
    }

    public function notes(): string
    {
        $own = LocalizedText::from($this->settings['notes'] ?? null);

        return $own->isEmpty() ? $this->field->notes() : $own->in();
    }

    /** Required fields are validated in the default language only (ADR-0006). */
    /** How the field shows in the page editor (ADR-0063). */
    public function editor(): FieldDisplay
    {
        return FieldDisplay::tryFrom(Value::string($this->settings['editor'] ?? '')) ?? FieldDisplay::Open;
    }

    /** Percent of a page editor card the field takes; 100 by default. */
    public function width(): int
    {
        $width = Value::int($this->settings['width'] ?? EditorSettings::FULL_WIDTH);

        return in_array($width, EditorSettings::WIDTHS, true) ? $width : EditorSettings::FULL_WIDTH;
    }

    /** Text shown in the empty input, in the current language. */
    public function placeholder(): string
    {
        return LocalizedText::from($this->settings['placeholder'] ?? null)->in();
    }

    /** A Lucide icon name shown before the label, or null. */
    public function icon(): ?string
    {
        $icon = Value::string($this->settings['icon'] ?? '');

        return $icon === '' ? null : $icon;
    }

    public function required(): bool
    {
        return (bool) ($this->settings['required'] ?? false);
    }

    /** Whether the search index holds this field's text on this template (ADR-0054). */
    public function searchable(): bool
    {
        return (bool) ($this->settings['searchable'] ?? false);
    }

    /** When the field is shown; null means always (ADR-0030). */
    public function showIf(): ?FieldCondition
    {
        return $this->condition('show_if');
    }

    /** When the field is required besides its required flag; null means never (ADR-0030). */
    public function requiredIf(): ?FieldCondition
    {
        return $this->condition('required_if');
    }

    /**
     * @param  callable(string): mixed  $valueOf  another field's value (shared, or in the default language)
     */
    public function isShown(callable $valueOf): bool
    {
        return ! $this->showIf() instanceof FieldCondition || $this->showIf()->matches($valueOf);
    }

    /**
     * Shown fields are required when flagged or when required_if matches; hidden fields never are.
     *
     * @param  callable(string): mixed  $valueOf
     */
    public function isRequired(callable $valueOf): bool
    {
        if (! $this->isShown($valueOf)) {
            return false;
        }

        return $this->required() || ($this->requiredIf() instanceof FieldCondition && $this->requiredIf()->matches($valueOf));
    }

    private function condition(string $key): ?FieldCondition
    {
        $condition = trim(Value::string($this->settings[$key] ?? ''));

        return $condition === '' ? null : FieldCondition::parse($condition);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        return $this->field->toSummary() + [
            'label' => $this->labels()->toStorage(),
            'required' => $this->required(),
            'show_if' => $this->settings['show_if'] ?? null,
            'required_if' => $this->settings['required_if'] ?? null,
        ];
    }
}
