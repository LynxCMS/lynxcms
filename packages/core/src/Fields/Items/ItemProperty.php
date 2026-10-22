<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Items;

use LynxCms\Contracts\Fields\Comparison;

/**
 * A value inside each item of a field that selectors may test, such as an image's width or tags
 * (HasItemProperties). Lists match with = and != only; translatable properties match the text
 * visitors see, with fallback.
 *
 * @api
 */
final readonly class ItemProperty
{
    public function __construct(
        public Comparison $comparison,
        public bool $isList = false,
        public bool $isTranslatable = false,
    ) {}
}
