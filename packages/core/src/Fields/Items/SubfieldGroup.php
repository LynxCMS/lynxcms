<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Items;

/**
 * Subfields of an item shown together: under a heading with help above and notes (and a link) below, or, for
 * the fields before the first group, with no heading at all.
 *
 * @api
 */
final readonly class SubfieldGroup
{
    /** @param  list<Subfield>  $subfields */
    public function __construct(
        public string $label,
        public string $description,
        public string $notes,
        public array $subfields,
        public string $linkLabel = '',
        public string $linkUrl = '',
    ) {}

    /** A link after the notes, such as to the docs; it opens in a new tab. */
    public function hasLink(): bool
    {
        return $this->linkLabel !== '' && $this->linkUrl !== '';
    }

    public function hasHeading(): bool
    {
        return $this->label !== '';
    }

    public function with(Subfield $subfield): self
    {
        return new self($this->label, $this->description, $this->notes, [...$this->subfields, $subfield], $this->linkLabel, $this->linkUrl);
    }
}
