<?php

declare(strict_types=1);

namespace LynxCms\Admin;

use Closure;
use LynxCms\Core\Pages\Page;

/**
 * Links modules add to the page editor's header for some pages, such as "Preview form" on form
 * pages. Modules register them in a service provider:
 * app(EditorLinks::class)->add(fn (Page $page): ?array => [...]).
 *
 * @api
 */
final class EditorLinks
{
    /** @var list<Closure(Page): (array{label: string, url: string}|null)> */
    private array $links = [];

    /**
     * @param  Closure(Page): (array{label: string, url: string}|null)  $link  null shows nothing for that page
     */
    public function add(Closure $link): void
    {
        $this->links[] = $link;
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    public function for(Page $page): array
    {
        return array_values(array_filter(array_map(fn (Closure $link): ?array => $link($page), $this->links)));
    }
}
