<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use LynxCms\Core\Pages\Page;

/**
 * The New page screen for ?parent={id}.
 *
 * @internal
 */
final class CreatePageController
{
    public function __invoke(Request $request): View
    {
        $parent = $request->query('parent');
        $page = is_string($parent) && ctype_digit($parent) ? lynx()->pages->get((int) $parent) : null;
        // A page whose template allows no children (ADR-0032) cannot be a parent.
        abort_unless($page instanceof Page && $page->template()->allowsChildren(), 404);

        return view('lynx-admin::pages.create', ['parentId' => (int) $parent]);
    }
}
