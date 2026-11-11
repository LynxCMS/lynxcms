<?php

declare(strict_types=1);

namespace LynxCms\Finder\Admin\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Locked;
use LynxCms\Admin\AdminAccess;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Support\Value;
use LynxCms\Finder\Actions\DeleteSearch;
use LynxCms\Finder\Actions\SaveSearch;
use LynxCms\Finder\Models\SavedSearch;
use LynxCms\Finder\Search\FinderQuery;

/**
 * Saved searches on the Find pages screen (ADR-0111): open one of the user's own or a shared one,
 * save the current search (as the open one, or as a new one) and delete one. Every write goes
 * through finder.save_search and finder.delete_search, which check ownership and finder.share.
 *
 * @internal
 */
trait ManagesSavedSearches
{
    /** The saved search the screen opened, if any. */
    #[Locked]
    public ?int $savedSearch = null;

    public string $saveName = '';

    public bool $saveShared = false;

    #[Locked]
    public bool $confirmingDelete = false;

    public function openSearch(int|string $id): void
    {
        $found = SavedSearch::visibleTo($this->owner())->find(Value::int($id));

        if (! $found instanceof SavedSearch) {
            return;
        }

        $this->replaceSearch(FinderQuery::fromArray($found->query));
        $this->savedSearch = $found->id;
        $this->saveName = $found->name;
        $this->saveShared = $found->is_shared;
    }

    public function saveSearch(bool $asNew = false): void
    {
        $input = ['name' => $this->saveName, 'query' => $this->search, 'shared' => $this->saveShared];

        if (! $asNew && $this->savedSearch !== null && $this->canChangeSaved()) {
            $input['search'] = $this->savedSearch;
        }

        try {
            $this->savedSearch = lynx()->run(SaveSearch::class, $input)->valueAs(SavedSearch::class)->id;
        } catch (AuthorizationException $refusal) {
            $this->notify($refusal->getMessage(), 'warning');

            return;
        }

        $this->dispatch('close-dialog-lynx-finder-save');
        $this->notify(__('lynx-finder::admin.saved.done'));
    }

    public function confirmDeleteSearch(): void
    {
        $this->confirmingDelete = $this->savedSearch !== null;
    }

    public function cancelDeleteSearch(): void
    {
        $this->confirmingDelete = false;
    }

    public function deleteSearch(): void
    {
        if ($this->savedSearch !== null) {
            lynx()->run(DeleteSearch::class, ['search' => $this->savedSearch]);
            $this->notify(__('lynx-finder::admin.saved.deleted'));
        }

        $this->forgetSavedSearch();
    }

    /**
     * The searches the user may open, by name.
     *
     * @return list<array{id: int, name: string, shared: bool}>
     */
    private function savedSearches(): array
    {
        return array_values(SavedSearch::visibleTo($this->owner())->get()
            ->map(fn (SavedSearch $search): array => ['id' => $search->id, 'name' => $search->name, 'shared' => $search->is_shared])
            ->all());
    }

    private function canShare(): bool
    {
        return app(AdminAccess::class)->allows('finder.share');
    }

    /** Whether the user may change the open saved search: its owner or a superuser. */
    private function canChangeSaved(): bool
    {
        $search = $this->savedSearch === null ? null : SavedSearch::query()->find($this->savedSearch);

        return $search instanceof SavedSearch && ($search->owner === $this->owner() || app(AdminAccess::class)->isSuperuser());
    }

    private function forgetSavedSearch(): void
    {
        $this->savedSearch = null;
        $this->saveName = '';
        $this->saveShared = false;
        $this->confirmingDelete = false;
    }

    private function owner(): string
    {
        return SavedSearch::ownerKey(app(ActionRunner::class)->actor());
    }
}
