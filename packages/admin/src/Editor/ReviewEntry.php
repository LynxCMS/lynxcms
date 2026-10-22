<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

/**
 * One changed value in a review: a field in one language (null for shared fields), with the
 * stored value and the new one, both ready for the editor.value view.
 *
 * @api
 */
final readonly class ReviewEntry
{
    public function __construct(
        public string $field,
        public string $label,
        public ?string $language,
        public string $type,
        public mixed $before,
        public mixed $after,
    ) {}
}
