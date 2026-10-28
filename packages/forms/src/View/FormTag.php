<?php

declare(strict_types=1);

namespace LynxCms\Forms\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Forms\FormRegistry;
use LynxCms\Forms\FormSchema;

/**
 * <x-lynx-form :form="$page->form" />: a form picked with the form field, or named by key,
 * rendered with the Livewire form on the current page. Renders nothing for no form, or one that
 * is not published. $livewire names a component extending FormComponent, for live extras.
 *
 * @api
 */
final class FormTag extends Component
{
    public ?string $key;

    public ?int $pageId;

    public function __construct(Page|PageArray|string|null $form = null, ?Page $page = null, public string $livewire = 'lynx-form')
    {
        $form = $form instanceof PageArray ? $form->first() : $form;
        $key = $form instanceof Page ? FormSchema::key($form) : $form;
        $this->key = $key !== null && $key !== '' && app(FormRegistry::class)->get($key) !== null ? $key : null;
        $this->pageId = $page?->id ?? $this->currentPageId();
    }

    public function shouldRender(): bool
    {
        return $this->key !== null;
    }

    public function render(): View
    {
        return view('lynx-forms::components.form');
    }

    /** The page the template renders, which views receive as $page. */
    private function currentPageId(): ?int
    {
        $page = view()->shared('page');

        return $page instanceof Page ? $page->id : null;
    }
}
