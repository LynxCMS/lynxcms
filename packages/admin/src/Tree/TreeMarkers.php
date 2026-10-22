<?php

declare(strict_types=1);

namespace LynxCms\Admin\Tree;

use Closure;
use LynxCms\Core\Pages\Page;

/**
 * Small icons modules show beside a page in the tree, such as a key on password-protected
 * pages. Modules register them in a service provider:
 * app(TreeMarkers::class)->add('page-password', fn (Page $page): ?array => ['icon' => 'key-round', 'label' => 'Password protected']).
 *
 * @api
 */
final class TreeMarkers
{
    /** @var array<string, Closure(Page): (array{icon: string, label: string}|null)> */
    private array $markers = [];

    /**
     * @param  Closure(Page): (array{icon: string, label: string}|null)  $marker  null shows nothing for that page; the label is read by screen readers and shown on hover
     */
    public function add(string $key, Closure $marker): void
    {
        $this->markers[$key] = $marker;
    }

    /**
     * @return list<array{key: string, icon: string, label: string}>
     */
    public function for(Page $page): array
    {
        $markers = [];

        foreach ($this->markers as $key => $marker) {
            $shown = $marker($page);

            if ($shown !== null) {
                $markers[] = ['key' => $key] + $shown;
            }
        }

        return $markers;
    }
}
