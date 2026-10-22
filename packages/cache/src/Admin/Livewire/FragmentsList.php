<?php

declare(strict_types=1);

namespace LynxCms\Cache\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Cache\Actions\ClearPageCache;
use LynxCms\Cache\FragmentEntry;
use LynxCms\Cache\Fragments\FragmentClearer;
use LynxCms\Cache\Fragments\Fragments;

/**
 * The fragment cache on the page cache screen: what is stored, what each fragment holds, and
 * clearing one, every copy of a name, or all of them.
 *
 * @internal
 */
final class FragmentsList extends Component
{
    use WithPagination;

    private const int PER_PAGE = 25;

    public string $search = '';

    /** The fragment whose contents show. */
    public ?int $viewing = null;

    public string $status = '';

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('page_cache.manage'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function view(int $id): void
    {
        $this->viewing = $this->viewing === $id ? null : $id;
    }

    /** One copy: clearing a single entry is not something agents need, so no action runs. */
    public function clear(int $id, FragmentClearer $clearer): void
    {
        $clearer->clearEntry($id);
        $this->viewing = null;
        $this->status = 'Fragment cleared.';
    }

    public function clearName(string $name): void
    {
        lynx()->run(ClearPageCache::class, ['fragment' => $name]);
        $this->status = "Every copy of {$name} was cleared.";
    }

    public function clearAll(): void
    {
        lynx()->run(ClearPageCache::class, ['fragments' => true]);
        $this->status = 'Every fragment was cleared.';
    }

    public function render(Fragments $fragments): View
    {
        $entries = FragmentEntry::query()
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')->orderBy('language')
            ->paginate(self::PER_PAGE);
        $viewed = $this->viewing === null ? null : FragmentEntry::query()->find($this->viewing);

        return view('lynx-cache::admin.livewire.fragments', [
            'entries' => $entries,
            'total' => FragmentEntry::query()->count(),
            'viewedHtml' => $viewed === null ? null : $fragments->html($viewed),
        ]);
    }
}
