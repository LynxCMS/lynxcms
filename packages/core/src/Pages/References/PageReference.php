<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\References;

use LynxCms\Core\Support\Value;

/**
 * One reference a page's field holds to another page (ADR-0145): a row of the page references index.
 *
 * @internal
 */
final readonly class PageReference
{
    public const string KIND_FIELD = 'field';

    public function __construct(
        public int $pageId,
        public int $linkedPageId,
        public int $fieldId,
        public ?int $languageId,
        public ?string $itemKey,
        public int $sort,
        public string $kind = self::KIND_FIELD,
    ) {}

    public static function fromRow(object $row): self
    {
        return new self(
            Value::int($row->page_id ?? null),
            Value::int($row->linked_page_id ?? null),
            Value::int($row->field_id ?? null),
            Value::nullableInt($row->language_id ?? null),
            Value::nullableString($row->item_key ?? null),
            Value::int($row->sort ?? null),
            Value::string($row->kind ?? null, self::KIND_FIELD),
        );
    }

    /** What the reference points at and where it sits, without its order: two rows with the same identity are the same reference. */
    public function identity(): string
    {
        return implode('|', [$this->linkedPageId, $this->fieldId, $this->languageId ?? '', $this->itemKey ?? '', $this->kind]);
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toRow(?string $createdAt): array
    {
        return [
            'page_id' => $this->pageId,
            'linked_page_id' => $this->linkedPageId,
            'field_id' => $this->fieldId,
            'language_id' => $this->languageId,
            'item_key' => $this->itemKey,
            'kind' => $this->kind,
            'sort' => $this->sort,
            'created_at' => $createdAt,
        ];
    }
}
