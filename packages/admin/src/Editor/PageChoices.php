<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Tree\DefaultPresenter;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * The input of a page reference field: the title search (default), or a select, radios or
 * checkboxes listing the pages its "templates" setting allows (the "input" setting). Lists suit
 * short ones, such as categories, so they stop at LIMIT pages.
 *
 * @internal
 */
final readonly class PageChoices
{
    public function __construct(private AdminAccess $access) {}

    public const int LIMIT = 100;

    /**
     * search, select, radios or checkboxes. Radios pick one and checkboxes several, whichever the
     * input setting names: "multiple" decides.
     *
     * @param  array<array-key, mixed>  $settings
     */
    public static function style(array $settings): string
    {
        $input = Value::string($settings['input'] ?? 'search');

        return match (true) {
            in_array($input, ['search', 'select'], true) => $input,
            self::isMultiple($settings) => 'checkboxes',
            default => 'radios',
        };
    }

    /**
     * True when the form holds one page id (a select or radios) instead of a list.
     *
     * @param  array<array-key, mixed>  $settings
     */
    public static function holdsOne(array $settings): bool
    {
        return in_array(self::style($settings), ['select', 'radios'], true) && ! self::isMultiple($settings);
    }

    /**
     * @param  array<array-key, mixed>  $settings
     */
    public static function isMultiple(array $settings): bool
    {
        return ($settings['multiple'] ?? true) !== false;
    }

    /**
     * Pages of the allowed templates the user may view, by title, then chosen pages the list does
     * not offer (another template, or past the limit), so saving keeps them. Excluded pages, such as
     * the page being edited, are left out unless already chosen.
     *
     * @param  array<array-key, mixed>  $settings
     * @param  list<int>  $chosen
     * @param  list<int>  $exclude
     * @return list<array{id: int, title: string}>
     */
    public function options(array $settings, array $chosen, array $exclude = []): array
    {
        $templates = Value::strings($settings['templates'] ?? []);
        $selector = 'include=all, sort=title, limit='.self::LIMIT
            .($templates === [] ? '' : ', template='.implode('|', $templates))
            .implode('', array_map(fn (int $id): string => ', id!='.$id, $exclude));
        $options = lynx()->pages->find($selector, scope: $this->access->viewScope())->map($this->option(...));
        $missing = array_values(array_diff($chosen, array_column($options, 'id')));

        if ($missing !== []) {
            array_push($options, ...lynx()->pages->find('id='.implode('|', $missing).', include=all')->map($this->option(...)));
        }

        return $options;
    }

    /**
     * What an empty list says: which templates' pages to add first, by label.
     *
     * @param  array<string, mixed>  $settings
     */
    public function emptyNote(array $settings): string
    {
        $labels = array_map(
            fn (string $name): string => lynx()->templates->find($name)?->labels()->in() ?? $name,
            Value::strings($settings['templates'] ?? []),
        );

        return $labels === []
            ? Value::string(__('lynx-admin::inputs.page_empty_any'))
            : Value::string(__('lynx-admin::inputs.page_empty', ['templates' => implode(Value::string(__('lynx-admin::inputs.page_empty_or')), $labels)]));
    }

    /**
     * @return array{id: int, title: string}
     */
    private function option(Page $page): array
    {
        return ['id' => (int) $page->id, 'title' => (new DefaultPresenter)->title($page)];
    }
}
