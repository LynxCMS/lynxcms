<?php

declare(strict_types=1);

namespace LynxCms\Seo\JsonLd;

use LynxCms\Core\Pages\Items\Item;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\TranslatableValue;
use LynxCms\Seo\Support\AbsoluteUrl;

/**
 * Reads a mapping source from a page or a repeater item, in the current language with fallback.
 * Values come as templates get them (a Choice, a PageArray, an image), for ValueConverter.
 *
 * @internal
 */
final class SourceReader
{
    public function read(Page|Item $holder, string $source): mixed
    {
        if (Mapping::isConstant($source)) {
            return trim(substr($source, 1));
        }

        if (Mapping::isBuiltIn($source)) {
            return $holder instanceof Page ? $this->builtIn($holder, $source) : null;
        }

        if ($holder instanceof Page && $holder->templateField($source) === null) {
            return null;
        }

        $value = $holder->get($source);

        return $value instanceof TranslatableValue ? $value->value() : $value;
    }

    private function builtIn(Page $page, string $source): mixed
    {
        return match ($source) {
            '@title' => $page->getUnformatted('title'),
            '@url' => AbsoluteUrl::of($page->localUrl(lynx()->languages->current())),
            '@published' => $page->publishedAt ?? $page->createdAt,
            '@modified' => $page->updatedAt,
            default => null,
        };
    }
}
