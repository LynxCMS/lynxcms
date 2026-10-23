<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin\Http;

use Illuminate\Contracts\View\View;
use LynxCms\Admin\AdminAccess;

/**
 * One submission, by its uuid.
 *
 * @internal
 */
final readonly class ShowSubmission
{
    public function __construct(private AdminAccess $access) {}

    public function __invoke(string $submission): View
    {
        abort_unless($this->access->allows('forms.view'), 403);

        return view('lynx-forms::admin.show', ['uuid' => $submission]);
    }
}
