<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Editor\LocalizedForm;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Actions\Strings\SaveStrings;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Strings\PageStrings as Strings;
use LynxCms\Core\Strings\StringCatalog;
use LynxCms\Core\Strings\StringDefinition;
use LynxCms\Core\Support\Value;

/**
 * The Texts tab of the page editor, and Setup > Site texts (ADR-0047): the strings templates
 * declare with @string, one input per language with the code text as placeholder. Saving sends
 * only changed texts through strings.save; an empty input puts the code text back. While the
 * page has a draft, its own texts are read from and saved to the draft (ADR-0048).
 *
 * @api
 */
final class PageStrings extends Component
{
    use Notifies;

    #[Locked]
    public int $pageId;

    /** "page" for a page's own strings, "site" for the strings every page shares. */
    #[Locked]
    public string $scope = 'page';

    /** @var list<string> string keys, in the order of $values (keys have dots, so the form uses positions) */
    #[Locked]
    public array $keys = [];

    /** @var list<array<string, string>> position => language code => text */
    public array $values = [];

    /** @var list<array<string, string>> */
    #[Locked]
    public array $original = [];

    #[Locked]
    public ?string $status = null;

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
    }

    public function mount(int $pageId, string $scope = 'page'): void
    {
        $this->pageId = $pageId;
        $this->scope = $scope === 'site' ? 'site' : 'page';
        $this->load();
    }

    public function save(): void
    {
        abort_unless(app(AdminAccess::class)->allows('pages.edit', $this->page()), 403);
        $this->status = null;
        $changes = [];

        foreach ($this->values as $position => $byLanguage) {
            $key = $this->keys[$position] ?? null;

            if ($key === null) {
                continue;
            }

            foreach ($byLanguage as $code => $text) {
                $text = trim(Value::string($text));

                if ($text !== trim($this->original[$position][$code] ?? '')) {
                    $changes[$code][$key] = $text === '' ? null : $text;
                }
            }
        }

        $isDraft = $this->draft() instanceof Page;

        foreach ($changes as $code => $values) {
            lynx()->run(SaveStrings::class, ['page' => $this->pageId, 'language' => $code, 'values' => $values, 'draft' => $isDraft]);
        }

        $this->load();
        if ($changes === []) {
            $this->notify(__('lynx-admin::pages.strings.no_changes'), 'warning');
        } else {
            $this->notify(__($isDraft ? 'lynx-admin::pages.strings.saved_draft' : 'lynx-admin::pages.strings.saved'));
        }

        if ($changes !== [] && ! $isDraft && $this->scope === 'page') {
            $this->dispatch('lynx-page-changed', revision: $this->page()->revision);
        }
    }

    /** The editor started, published or discarded a draft, or the page changed otherwise. */
    #[On('lynx-page-changed')]
    public function reload(): void
    {
        $this->load();
    }

    public function render(): View
    {
        $groups = [];
        $definitions = $this->definitions();

        foreach ($this->keys as $position => $key) {
            if (isset($definitions[$key])) {
                $groups[str_contains($key, '.') ? strstr($key, '.', true) : ''][$position] = $definitions[$key];
            }
        }

        return view('lynx-admin::livewire.page-strings', [
            'groups' => $groups,
            'languages' => LocalizedForm::languages(),
            'canEdit' => app(AdminAccess::class)->allows('pages.edit', $this->page()),
            // String keys and the views declaring them are developer details.
            'isSuperuser' => app(AdminAccess::class)->isSuperuser(),
        ]);
    }

    private function load(): void
    {
        $codes = array_column(LocalizedForm::languages(), 'code');
        $page = $this->draft() ?? $this->page();
        $this->keys = [];
        $this->values = [];

        foreach ($this->definitions() as $key => $definition) {
            $edits = app(Strings::class)->edits($page, $definition);
            $this->keys[] = $key;
            $this->values[] = array_map(fn (string $code): string => $edits[$code] ?? '', array_combine($codes, $codes));
        }

        $this->original = $this->values;
    }

    /**
     * @return array<string, StringDefinition>
     */
    private function definitions(): array
    {
        if ($this->scope === 'site') {
            return app(StringCatalog::class)->siteStrings();
        }

        return array_filter(app(StringCatalog::class)->forTemplate((string) $this->page()->template()->name), fn (StringDefinition $definition): bool => ! $definition->isSite());
    }

    /** The draft whose texts the tab edits; site texts are never drafted. */
    private function draft(): ?Page
    {
        return $this->scope === 'page' ? lynx()->pages->draft($this->pageId) : null;
    }

    private function page(): Page
    {
        return lynx()->pages->get($this->pageId) ?? abort(404);
    }
}
