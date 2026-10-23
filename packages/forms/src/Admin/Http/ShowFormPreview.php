<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin\Http;

use Illuminate\Contracts\View\View;
use LynxCms\Admin\AdminAccess;

/**
 * A form as visitors see it, with the default form look.
 *
 * @internal
 */
final readonly class ShowFormPreview
{
    private const string STYLESHEET = __DIR__.'/../../../resources/css/forms.css';

    public function __construct(private AdminAccess $access) {}

    public function __invoke(string $form): View
    {
        abort_unless($this->access->allows('forms.view'), 403);

        return view('lynx-forms::admin.preview', ['form' => $form, 'css' => (string) file_get_contents(self::STYLESHEET)]);
    }
}
