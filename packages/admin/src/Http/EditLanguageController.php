<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Illuminate\Contracts\View\View;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Core\Models\Language;

/**
 * The Languages, New language and Edit language screens. A controller rather than closures so
 * route:cache keeps working.
 *
 * @internal
 */
final class EditLanguageController
{
    public function index(AdminAccess $access): View
    {
        abort_unless($access->allows('schema.manage'), 403);

        return view('lynx-admin::languages.index');
    }

    public function edit(AdminAccess $access, SchemaOwners $owners, ?string $language = null): View
    {
        abort_unless($access->allows('schema.manage'), 403);
        abort_if($language === null && ! $owners->isEditingEnabled(), 403);
        abort_unless($language === null || lynx()->languages->find($language) instanceof Language, 404);

        return view('lynx-admin::languages.edit', ['language' => $language]);
    }
}
