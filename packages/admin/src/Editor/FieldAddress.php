<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Pages\Page;

/**
 * The admin address that opens a page's editor at one field (ADR-0145): on the field's tab, in the
 * language given, scrolled to the field or to one repeater or matrix item. For "edit this" links
 * on the site and in modules:
 *
 *     <a href="{{ FieldAddress::url($page, 'sections', item: $item->key, language: 'pt') }}">Edit</a>
 *
 * @api
 */
final class FieldAddress
{
    /**
     * @param  string|null  $item  the key of a repeater or matrix item, which stays the same when items are reordered
     * @param  string|null  $language  a language code, for translatable fields
     */
    public static function url(Page|int $page, string $field, ?string $item = null, ?string $language = null): string
    {
        return route('lynx.admin.pages.edit', array_filter([
            'page' => $page instanceof Page ? (int) $page->id : $page,
            'field' => $field,
            'item' => $item,
            'lang' => $language,
        ], fn (int|string|null $value): bool => $value !== null && $value !== ''));
    }
}
