<?php

declare(strict_types=1);

namespace LynxCms\Finder\Admin\Concerns;

use Illuminate\Validation\ValidationException;
use LynxCms\Admin\Livewire\Concerns\ChangesPages;
use LynxCms\Core\Pages\Page;
use LynxCms\Finder\FinderResults;

/**
 * Publish, unpublish, hide, show or trash the ticked results (ChangesPages). A ticked id must be a
 * page the finder searches and the user may view; the pages actions then check the change itself.
 *
 * @internal
 */
trait ActsOnResults
{
    use ChangesPages;

    private function changeablePage(int $id): Page
    {
        return app(FinderResults::class)->page($id)
            ?? throw ValidationException::withMessages(['page' => __('lynx-finder::admin.bulk.not_found')]);
    }

    private function trashConfirming(): void {}
}
