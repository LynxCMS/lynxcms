<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LynxCms\Admin\Locale\SaveAdminLocale;
use LynxCms\Core\Actions\Runner\ActionRunner;

/**
 * The language choices in the user menu (ADR-0097).
 *
 * @internal
 */
final class AdminLocaleController
{
    public function __invoke(Request $request, ActionRunner $runner): RedirectResponse
    {
        $runner->run(SaveAdminLocale::class, ['locale' => $request->string('locale')->toString()]);

        return back();
    }
}
