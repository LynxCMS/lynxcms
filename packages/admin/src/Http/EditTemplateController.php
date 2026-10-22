<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Illuminate\Contracts\View\View;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Core\Models\Template;

/**
 * The New template and Edit template screens. A controller rather than a closure so route:cache keeps working.
 *
 * @internal
 */
final class EditTemplateController
{
    public function __invoke(?string $template = null): View
    {
        abort_if($template === null && ! app(SchemaOwners::class)->isEditingEnabled(), 403);
        abort_unless($template === null || lynx()->templates->find($template) instanceof Template, 404);

        return view('lynx-admin::templates.edit', ['template' => $template]);
    }
}
