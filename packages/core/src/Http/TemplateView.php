<?php

declare(strict_types=1);

namespace LynxCms\Core\Http;

use Illuminate\Contracts\View\Factory as ViewFactory;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * Renders a page with its view: templates/{template} (lynx.views_path), else Lynx's plain page.
 *
 * @internal
 */
final class TemplateView
{
    /** The page's HTML, with $page (the page, or its draft preview) and $lynx in the view. */
    public static function render(Page $page, ViewFactory $views, ?Page $shown = null): string
    {
        $view = Value::string(config('lynx.views_path', 'templates')).'.'.$page->template()->name;

        return $views->make($views->exists($view) ? $view : 'lynx::page', ['page' => $shown ?? $page, 'lynx' => lynx()])->render();
    }
}
