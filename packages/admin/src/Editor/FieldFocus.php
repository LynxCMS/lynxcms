<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Admin\AdminAccess;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\Layout\TemplateLayout;

/**
 * The field a field address names (FieldAddress), checked: a field of the page's template the user
 * may edit, an item key and a language that exist. Anything else opens the editor as usual.
 *
 * @internal
 */
final class FieldFocus
{
    /**
     * @param  array<array-key, mixed>  $query  the request's query string
     * @return array{field: string, item: string|null, language: string|null, tab: string}|null
     */
    public static function from(Page $page, array $query): ?array
    {
        $field = Value::string($query['field'] ?? null);

        if ($field === '' || $page->templateField($field) === null || ! app(AdminAccess::class)->allows('pages.edit', $page)) {
            return null;
        }

        $item = Value::string($query['item'] ?? null);
        $code = Value::string($query['lang'] ?? null);

        return [
            'field' => $field,
            'item' => preg_match('/^[0-9A-Z]{26}$/', $item) === 1 ? $item : null,
            'language' => $code === '' ? null : app(LanguageRegistry::class)->find($code)?->code,
            'tab' => TemplateLayout::for($page->template())->tabOf($field),
        ];
    }
}
