<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Actions\Pages\SetNotFoundPage;
use LynxCms\Core\Pages\NotFoundPage;

/**
 * Settings > General > Page not found: the page shown, with status 404, for URLs that match no
 * page (ADR-0085), or none for the framework's own 404. Saves through pages.set_not_found.
 *
 * @internal
 */
final class NotFoundPageForm extends Component
{
    use Notifies;

    /** @var list<int> the chosen page, as the page picker holds it */
    public array $page = [];

    #[Locked]
    public ?string $status = null;

    /** Runs on mount and on every Livewire update, so the checks also guard /livewire/update. */
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(RoleList::currentUserIsSuperuser(), 403, 'Only superusers choose the page not found.');
    }

    public function mount(NotFoundPage $notFound): void
    {
        $id = $notFound->id();
        $this->page = $id === null ? [] : [$id];
    }

    public function save(): void
    {
        lynx()->run(SetNotFoundPage::class, ['page' => $this->page[0] ?? null]);
        $this->notify(($this->page[0] ?? null) === null ? 'Saved. Missing pages show the plain 404.' : 'Saved.');
    }

    public function render(NotFoundPage $notFound): View
    {
        $chosen = $notFound->id();

        return view('lynx-admin::livewire.not-found-page-form', [
            'isShown' => $chosen === null || $notFound->page() !== null,
            'editUrl' => $chosen === null ? null : route('lynx.admin.pages.edit', $chosen),
        ]);
    }
}
