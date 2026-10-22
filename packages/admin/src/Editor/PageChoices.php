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
final class PageChoices
{
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
     * not offer (another template, or past the limit), so saving keeps them.
     *
     * @param  array<array-key, mixed>  $settings
     * @param  list<int>  $chosen
     * @return list<array{id: int, title: string}>
     */
    public function options(array $settings, array $chosen): array
    {
        $templates = Value::strings($settings['templates'] ?? []);
        $selector = 'include=all, sort=title, limit='.self::LIMIT.($templates === [] ? '' : ', template='.implode('|', $templates));
        $options = lynx()->pages->find($selector, scope: app(AdminAccess::class)->viewScope())->map($this->option(...));
        $missing = array_values(array_diff($chosen, array_column($options, 'id')));

        if ($missing !== []) {
            array_push($options, ...lynx()->pages->find('id='.implode('|', $missing).', include=all')->map($this->option(...)));
        }

        return $options;
    }

    /**
     * @return array{id: int, title: string}
     */
    private function option(Page $page): array
    {
        return ['id' => (int) $page->id, 'title' => (new DefaultPresenter)->title($page)];
    }
}
