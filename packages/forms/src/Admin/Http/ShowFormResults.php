<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin\Http;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use LynxCms\Admin\AdminAccess;
use LynxCms\Forms\FormRegistry;
use LynxCms\Forms\Results\FormResults;

/**
 * A form's answers summed up per question,, such as a survey's results.
 *
 * @internal
 */
final readonly class ShowFormResults
{
    public function __construct(private AdminAccess $access, private FormRegistry $forms, private FormResults $results) {}

    public function __invoke(Request $request, string $form): View
    {
        abort_unless($this->access->allows('forms.view'), 403);
        $definition = $this->forms->get($form) ?? abort(404);
        $page = $request->integer('page') ?: null;

        return view('lynx-forms::admin.results', [
            'form' => $definition,
            'results' => $this->results->summarize($definition, $page),
            'pages' => $this->results->pages($definition),
            'page' => $page,
        ]);
    }
}
