<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

/**
 * A field's value in one language as the editor shows it: the stored value, or the value a
 * visitor gets through the fallback chain and the language it comes from.
 *
 * @internal
 */
final readonly class LanguageValue
{
    public function __construct(
        public mixed $value,
        public bool $isOwn,
        public ?string $fallsBackTo = null,
    ) {}

    public function isEmpty(): bool
    {
        return ! $this->isOwn && $this->fallsBackTo === null;
    }
}
