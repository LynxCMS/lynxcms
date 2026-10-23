<?php

declare(strict_types=1);

namespace LynxCms\Seo\Admin\Http;

use Illuminate\Contracts\View\View;
use LynxCms\Admin\AdminAccess;
use LynxCms\Seo\SeoResolver;

/**
 * A page as a search result and a social card. Anyone who may see the page in the admin may
 * preview it.
 *
 * @internal
 */
final readonly class ShowSeoPreview
{
    public function __construct(private AdminAccess $access, private SeoResolver $resolver) {}

    public function __invoke(int $page): View
    {
        $found = lynx()->pages->get($page);
        abort_if($found === null || ! $found->template()->hasUrl() || ! $this->access->allows('pages.view'), 404);

        return view('lynx-seo::admin.preview', ['page' => $found, 'seo' => $this->resolver->resolve($found)]);
    }
}
