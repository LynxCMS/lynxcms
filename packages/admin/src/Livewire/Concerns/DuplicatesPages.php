<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Pages\Page;

/**
 * Duplicate in the page tree's row menu: pages.duplicate, alone or with the pages below, then
 * the copy opens in the editor. A refusal (the home page, the user's permissions, the template
 * rules) shows above the tree like the other row actions.
 *
 * @internal
 */
trait DuplicatesPages
{
    public function duplicate(int $id, bool $children = false): void
    {
        $copy = null;

        $this->attempt(function () use ($id, $children, &$copy): void {
            try {
                $copy = lynx()->pages->duplicate($id, children: $children, titlePrefix: __('lynx-admin::pages.copy_prefix'));
            } catch (AuthorizationException) {
                throw ValidationException::withMessages(['page' => __('lynx-admin::pages.actions.duplicate_denied')]);
            }
        });

        if ($copy instanceof Page) {
            $this->redirectRoute('lynx.admin.pages.edit', ['page' => $copy->id], navigate: true);
        }
    }

    /** Run a write; a validation failure becomes the visible error instead of a 500. */
    abstract protected function attempt(Closure $write): void;
}
