<?php

declare(strict_types=1);

namespace LynxCms\Contracts\Fields;

/**
 * What a formatter knows about the value it formats.
 *
 * @api
 */
final readonly class FormatContext
{
    public function __construct(
        public string $field,
        public string $fieldType,
        public ?string $language,
        public ?int $pageId = null,
    ) {}
}
