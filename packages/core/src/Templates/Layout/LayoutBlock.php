<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates\Layout;

/**
 * One entry of a column: a field on its own, or a section with its fields.
 *
 * @api
 */
final class LayoutBlock
{
    /**
     * @param  list<string>  $fields  the section's fields, in order
     */
    private function __construct(
        public readonly ?string $field,
        public readonly ?LayoutSection $section,
        public array $fields = [],
    ) {}

    public static function field(string $name): self
    {
        return new self($name, null);
    }

    public static function section(LayoutSection $section): self
    {
        return new self(null, $section);
    }

    public function isSection(): bool
    {
        return $this->section instanceof LayoutSection;
    }
}
