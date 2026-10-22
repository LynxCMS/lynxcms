<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminBranches;
use LynxCms\Core\Pages\Page;

/**
 * The page editor screen. A controller rather than a closure so route:cache keeps working.
 *
 * @internal
 */
final class EditPageController
{
    public function __invoke(int $page): View|RedirectResponse
    {
        $found = lynx()->pages->get($page);
        abort_unless($found instanceof Page, 404);
        abort_unless(app(AdminAccess::class)->allows('pages.view', $found), 403);

        // Pages a module edits on its own screen (menus) open there instead.
        $moduleScreen = app(AdminBranches::class)->editUrlFor($found);

        if ($moduleScreen !== null) {
            return redirect($moduleScreen);
        }

        return view('lynx-admin::pages.edit', [
            'pageId' => $page,
            'viewUrl' => $found->template()->hasUrl() ? $found->url() : null,
        ]);
    }
}
