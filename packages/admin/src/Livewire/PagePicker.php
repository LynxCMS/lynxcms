<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Modelable;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Editor\PageSearch;
use LynxCms\Admin\Tree\DefaultPresenter;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * Chooses pages for a page reference field: the chosen pages with Remove buttons and a title
 * search to add more. Bound to its parent with wire:model, e.g. in the page editor.
 *
 * @api
 */
final class PagePicker extends Component
{
    private const int SEARCH_LIMIT = 8;

    /** @var list<int> chosen page ids, in order */
    #[Modelable]
    public array $value = [];

    /** False: choosing a page replaces the current one (the field's "multiple" setting). */
    #[Locked]
    public bool $multiple = true;

    /** Most pages the field holds (its "max" setting); 0 means no limit. */
    #[Locked]
    public int $max = 0;

    /** @var list<string> template names that may be chosen; empty means any */
    #[Locked]
    public array $templates = [];

    public string $search = '';

    /**
     * Pages the field held when it opened: shown even outside what the user may view, so the
     * value never looks empty and gets dropped on save.
     *
     * @var list<int>
     */
    #[Locked]
    public array $known = [];

    /** Text of the empty search box; null uses a default that fits single or multiple choice. */
    #[Locked]
    public ?string $placeholder = null;

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @param  array<array-key, mixed>  $templates
     */
    public function mount(array $value = [], bool $multiple = true, array $templates = [], ?string $placeholder = null, int $max = 0): void
    {
        $this->value = Value::ints($value);
        $this->known = $this->value;
        $this->multiple = $multiple;
        $this->max = max(0, $max);
        $this->templates = Value::strings($templates);
        $this->placeholder = $placeholder;
    }

    public function add(int $id): void
    {
        $page = lynx()->pages->get($id);

        if (! $page instanceof Page || ! $this->isAllowed($page) || $this->isFull() || app(AdminAccess::class)->viewable([$id]) === []) {
            return;
        }

        $ids = Value::ints($this->value);
        $this->value = $this->multiple ? array_values(array_unique([...$ids, $id])) : [$id];
        $this->search = '';
    }

    public function remove(int $id): void
    {
        $this->value = array_values(array_diff(Value::ints($this->value), [$id]));
    }

    public function render(): View
    {
        return view('lynx-admin::livewire.page-picker', ['chosen' => $this->chosen(), 'results' => $this->isFull() ? [] : $this->results(), 'isFull' => $this->isFull()]);
    }

    /** A field that holds several pages and has its most: removing one makes room for another. */
    private function isFull(): bool
    {
        return $this->multiple && $this->max > 0 && count(Value::ints($this->value)) >= $this->max;
    }

    /**
     * @return list<array{id: int, title: string, url: string}>
     */
    private function chosen(): array
    {
        $ids = Value::ints($this->value);
        $viewable = app(AdminAccess::class)->viewable(array_values(array_diff($ids, $this->known)));
        $ids = array_values(array_filter($ids, fn (int $id): bool => in_array($id, $this->known, true) || in_array($id, $viewable, true)));

        if ($ids === []) {
            return [];
        }

        $pages = [];

        // Pages the field held show even outside the user's view scope: hiding them would look
        // like an empty field, and saving would then drop the reference.
        foreach (lynx()->pages->find('id='.implode('|', $ids).', include=all')->map(fn (Page $page): Page => $page) as $page) {
            $pages[(int) $page->id] = $this->row($page);
        }

        return array_values(array_filter(array_map(fn (int $id): ?array => $pages[$id] ?? null, $ids)));
    }

    /**
     * Pages whose title contains the search text, leaving out chosen ones.
     *
     * @return list<array{id: int, title: string, url: string}>
     */
    private function results(): array
    {
        $pages = app(PageSearch::class)->find($this->search, self::SEARCH_LIMIT + count($this->value), $this->templates);
        $chosen = Value::ints($this->value);
        $rows = array_filter($pages->map(fn (Page $page): ?array => in_array((int) $page->id, $chosen, true) ? null : $this->row($page)));

        return array_slice(array_values($rows), 0, self::SEARCH_LIMIT);
    }

    /**
     * @return array{id: int, title: string, url: string}
     */
    private function row(Page $page): array
    {
        return [
            'id' => (int) $page->id,
            'title' => (new DefaultPresenter)->title($page),
            'url' => $page->template()->hasUrl() ? $page->url() : '',
        ];
    }

    private function isAllowed(Page $page): bool
    {
        return $this->templates === [] || in_array((string) $page->template()->name, $this->templates, true);
    }
}
