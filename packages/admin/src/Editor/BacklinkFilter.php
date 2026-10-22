<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

/**
 * Templates whose pages the Settings tab leaves out of "Linked from". Modules exclude pages that
 * link through their own screens, e.g. menu items: app(BacklinkFilter::class)->exclude('menu-item').
 *
 * @api
 */
final class BacklinkFilter
{
    /** @var array<string, true> */
    private array $excluded = [];

    public function exclude(string ...$templates): void
    {
        $this->excluded += array_fill_keys($templates, true);
    }

    /** A selector for $lynx->pages->backlinks(), empty when nothing is excluded. */
    public function selector(): string
    {
        return $this->excluded === [] ? '' : 'template!='.implode('|', array_keys($this->excluded));
    }
}
