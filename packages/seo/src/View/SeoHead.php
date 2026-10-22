<?php

declare(strict_types=1);

namespace LynxCms\Seo\View;

use Illuminate\Contracts\View\View;
use LynxCms\Core\Pages\Page;
use LynxCms\Seo\SeoResolver;

/**
 * The <head> tags for a page, shared by <x-lynx-seo> and @seo.
 *
 * @internal
 */
final readonly class SeoHead
{
    public function __construct(private SeoResolver $resolver) {}

    /** @param array<string, string> $overrides see SeoResolver::OVERRIDES */
    public function view(Page $page, array $overrides = []): View
    {
        return view('lynx-seo::head', ['seo' => $this->resolver->resolve($page, $overrides)]);
    }

    /** @param array<string, string> $overrides see SeoResolver::OVERRIDES */
    public function html(Page $page, array $overrides = []): string
    {
        return $this->view($page, $overrides)->render();
    }
}
