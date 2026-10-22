<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Illuminate\Contracts\View\View;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Core\Models\Field;

/**
 * The New field and Edit field screens. A controller rather than a closure so route:cache keeps working.
 *
 * @internal
 */
final class EditFieldController
{
    public function __invoke(?string $field = null): View
    {
        abort_if($field === null && ! app(SchemaOwners::class)->isEditingEnabled(), 403);
        abort_unless($field === null || lynx()->fields->find($field) instanceof Field, 404);

        return view('lynx-admin::fields.edit', ['field' => $field]);
    }
}
