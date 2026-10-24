<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use DateTimeInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Modelable;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Pages\TrashPage;
use LynxCms\Core\Fields\Choices\Choice;
use LynxCms\Core\Fields\Choices\ChoiceList;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Support\Value;
use Stringable;

/**
 * The page table input (ADR-0058): the field's pages in a table with the chosen columns, a form
 * to make a new one, and edit, move and remove per row. New pages are saved at once, hidden,
 * under the page (or the field's parent); the list itself is saved with the page.
 *
 * @internal
 */
final class PageTableInput extends Component
{
    /** @var list<int> */
    #[Modelable]
    public array $value = [];

    #[Locked]
    public ?int $pageId = null;

    /** @var list<string> */
    #[Locked]
    public array $templates = [];

    /** @var list<string> */
    #[Locked]
    public array $columns = ['title'];

    /** Path new items go under; empty for the page itself. */
    #[Locked]
    public string $parent = '';

    #[Locked]
    public bool $trashOnRemove = false;

    #[Locked]
    public bool $disabled = false;

    /**
     * Pages the field held when it opened, or that were created here: shown even outside what
     * the user may view, so the value never looks empty.
     *
     * @var list<int>
     */
    #[Locked]
    public array $known = [];

    public string $template = '';

    public string $title = '';

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @param  array<array-key, mixed>  $settings  the field's settings
     */
    public function mount(array $value = [], ?int $pageId = null, array $settings = [], bool $disabled = false): void
    {
        $this->value = Value::ints($value);
        $this->known = $this->value;
        $this->pageId = $pageId;
        $this->templates = Value::strings($settings['templates'] ?? []);
        $this->columns = Value::strings($settings['columns'] ?? []) ?: ['title'];
        $this->parent = Value::string($settings['parent'] ?? '');
        $this->trashOnRemove = ($settings['remove'] ?? 'keep') === 'trash';
        $this->disabled = $disabled;
        $this->template = $this->templates[0] ?? '';
    }

    /** Saves a new hidden page with the title given and adds it to the end of the list. */
    public function create(): void
    {
        abort_if($this->disabled || ($this->parent === '' && $this->pageId === null), 403);
        $this->validate(['title' => ['required', 'string', 'max:255'], 'template' => ['required', Rule::in($this->templates)]]);

        $page = lynx()->run(SavePage::class, [
            'template' => $this->template,
            'parent' => $this->parent !== '' ? $this->parent : $this->pageId,
            'values' => ['title' => [(string) lynx()->languages->default()->code => trim($this->title)]],
            'status' => ['hidden'],
        ])->valueAs(Page::class);

        $this->value = [...$this->value, (int) $page->id];
        $this->known = [...$this->known, (int) $page->id];
        $this->title = '';
    }

    /** Takes the page out of the list; with "remove": trash, also moves it to the trash. */
    public function remove(int $id): void
    {
        abort_if($this->disabled, 403);
        $this->value = array_values(array_diff($this->value, [$id]));

        if ($this->trashOnRemove) {
            lynx()->run(TrashPage::class, ['page' => $id]);
        }
    }

    /** Moves a row one place up (-1) or down (1). */
    public function move(int $id, int $step): void
    {
        $from = array_search($id, $this->value, true);
        $to = $from === false ? -1 : $from + $step;

        if ($from !== false && $to >= 0 && $to < count($this->value)) {
            $value = $this->value;
            [$value[$from], $value[$to]] = [$value[$to], $value[$from]];
            $this->value = array_values($value);
        }
    }

    public function render(): View
    {
        $viewable = app(AdminAccess::class)->viewable(array_values(array_diff($this->value, $this->known)));
        $pages = app(PageLoader::class)->many(array_values(array_filter($this->value, fn (int $id): bool => in_array($id, $this->known, true) || in_array($id, $viewable, true))));
        $rows = [];

        foreach ($pages as $page) {
            if ($page instanceof Page) {
                $rows[] = ['id' => (int) $page->id, 'cells' => array_map(fn (string $column): string => $this->cell($page, $column), $this->columns)];
            }
        }

        return view('lynx-admin::livewire.page-table-input', [
            'rows' => $rows,
            'labels' => array_map(fn (string $column): string => Value::string(lynx()->fields->find($column)?->label, $column), $this->columns),
            'canCreate' => ! $this->disabled && ($this->parent !== '' || $this->pageId !== null),
        ]);
    }

    /** A column's value as short text. */
    private function cell(Page $page, string $column): string
    {
        if ($page->templateField($column) === null) {
            return '';
        }

        $value = $page->get($column);

        return match (true) {
            $value instanceof Choice => $value->label(),
            $value instanceof ChoiceList => implode(', ', $value->labels()),
            $value instanceof PageArray => implode(', ', $value->map(fn (Page $item): string => Value::string($item->getUnformatted('title')))),
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i'),
            is_scalar($value), $value instanceof Stringable => mb_strimwidth(strip_tags((string) $value), 0, 80, '…'),
            default => '',
        };
    }
}
