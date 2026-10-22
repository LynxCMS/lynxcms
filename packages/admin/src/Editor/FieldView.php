<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Templates\EditorSettings;
use LynxCms\Core\Templates\FieldDisplay;

/**
 * One template field in the editor: its definition and its value per language (shared fields
 * have a single entry under the default language code).
 *
 * @api
 */
final readonly class FieldView
{
    /**
     * @param  array<string, LanguageValue>  $values  keyed by language code
     * @param  array<string, mixed>  $settings  the field's settings, e.g. "multiple" for page fields
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $type,
        public bool $isTranslatable,
        public bool $isRequired,
        public ?string $description,
        public array $values,
        public array $settings = [],
        public ?string $notes = null,
        public ?int $pageId = null,
        public ?string $fieldName = null,
        public FieldDisplay $display = FieldDisplay::Open,
        public int $width = EditorSettings::FULL_WIDTH,
        public ?string $placeholder = null,
        public ?string $icon = null,
    ) {}

    /** Whether the field starts closed in the editor (ADR-0063): by its mode and whether it has a value. */
    public function startsCollapsed(): bool
    {
        return $this->display->startsCollapsed(array_any($this->values, fn (LanguageValue $value): bool => $value->isOwn));
    }

    /** The field's name; $name may be a unique id when the field is a repeater or matrix subfield. */
    public function field(): string
    {
        return $this->fieldName ?? $this->name;
    }
}
