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
use LynxCms\Admin\Editor\LinkPages;
use LynxCms\Core\Fields\LinkRelSanitizer;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * The link dialog of rich text fields, one per admin page (in the layout). A rich text editor
 * opens it with the lynx-link-open event and its link; the dialog answers with lynx-link-chosen,
 * carrying the same requestId, so only that editor applies it (an empty href removes the link).
 * Editors type an address or words to find a page, browse the page tree, pick a section of a
 * page (its headings) or a file of this page or the page chosen (LinkPages); the Attributes tab
 * sets title, target and rel.
 *
 * @internal
 */
final class LinkDialog extends Component
{
    private const int MIN_SEARCH = 2;

    /** The Select panels: pages, sections of a page, files. */
    private const array PANELS = ['pages', 'anchors', 'files'];

    /** Addresses that never run script: web, mail and phone links, paths on the site, anchors. */
    private const string SAFE_HREF = '~^(https?://[^\s/]|mailto:|tel:|/(?!/)|#|\?)~i';

    #[Locked]
    public string $requestId = '';

    #[Locked]
    public ?int $pageId = null;

    /** Language code of the text being edited: page links use their address in it. */
    #[Locked]
    public string $language = '';

    /** True when the dialog edits an existing link, which can then be removed. */
    #[Locked]
    public bool $isEditing = false;

    public string $href = '';

    public string $text = '';

    public string $title = '';

    public string $target = '';

    /** @var list<string> chosen relations, e.g. ["nofollow"] */
    public array $rel = [];

    /** "", or one of PANELS: the open Select panel. */
    public string $panel = '';

    /** The page last chosen: its sections and files are offered too. */
    #[Locked]
    public ?int $chosenPageId = null;

    /** Words typed in "Select page": while set, matching pages replace the tree. */
    public string $pageQuery = '';

    /** The page whose children "Select page" lists; null starts at the home page. */
    public ?int $browseId = null;

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
    }

    #[On('lynx-link-open')]
    public function open(string $requestId, string $href = '', string $text = '', string $title = '', string $target = '', string $rel = '', ?int $pageId = null, string $language = ''): void
    {
        $this->resetErrorBag();
        $this->requestId = $requestId;
        $this->pageId = $pageId;
        $this->language = (lynx()->languages->find($language) ?? lynx()->languages->default())->code;
        [$this->href, $this->text, $this->title] = [trim($href), $text, $title];
        $this->isEditing = $this->href !== '';
        $this->target = array_key_exists($target, $this->targets()) ? $target : '';
        $this->rel = $this->relations($rel);
        [$this->panel, $this->browseId, $this->pageQuery, $this->chosenPageId] = ['', null, '', null];
        $this->dispatch('open-dialog-lynx-link');
    }

    public function show(string $panel): void
    {
        $this->panel = in_array($panel, self::PANELS, true) && $panel !== $this->panel ? $panel : '';
    }

    public function browse(?int $id): void
    {
        $this->browseId = $id;
        $this->panel = 'pages';
    }

    public function choosePage(int $id): void
    {
        $page = lynx()->pages->get($id);

        if (! $page instanceof Page || ! $page->template()->hasUrl() || app(AdminAccess::class)->viewable([$id]) === []) {
            return;
        }

        $this->choose($page->localUrl($this->language), lynx()->language($this->language, fn (): string => Value::string($page->title)));
        $this->chosenPageId = $id;
    }

    /** A section: of the page chosen ("/about/#team"), else of the page being edited ("#team"). */
    public function chooseAnchor(string $id): void
    {
        $page = $this->chosenPageId === null ? null : lynx()->pages->get($this->chosenPageId);
        $base = $page instanceof Page ? $page->localUrl($this->language) : '';
        $this->choose($base.'#'.$id, '');
    }

    /** A file of the page being edited, or of the page chosen when $ofChosen. */
    public function chooseFile(int $index, bool $ofChosen = false): void
    {
        $file = app(LinkPages::class)->files($ofChosen ? $this->chosenPageId : $this->pageId)[$index] ?? null;

        if ($file !== null) {
            $this->choose($file['url'], $file['name']);
        }
    }

    public function insert(): void
    {
        $this->validate([
            'href' => ['required', 'string', 'max:2048', 'regex:'.self::SAFE_HREF],
            'title' => ['nullable', 'string', 'max:255'],
            'target' => ['nullable', 'in:'.implode(',', array_keys($this->targets()))],
            'rel' => ['array'],
            'rel.*' => ['in:'.implode(',', array_keys($this->rels()))],
        ], ['href.regex' => 'Enter an address starting with https://, mailto: or tel:, a path starting with /, or an #anchor.']);

        $this->answer(trim($this->href));
    }

    public function unlink(): void
    {
        $this->answer('');
    }

    public function render(): View
    {
        $pages = app(LinkPages::class);
        $hasOtherPage = $this->chosenPageId !== null && $this->chosenPageId !== $this->pageId;

        return view('lynx-admin::livewire.link-dialog', [
            'matches' => $this->requestId === '' ? [] : $this->matches(),
            'browsing' => $this->panel === 'pages' ? $pages->browse($this->browseId, $this->language) : null,
            'found' => $this->panel === 'pages' && mb_strlen(trim($this->pageQuery)) >= self::MIN_SEARCH ? $pages->search($this->pageQuery, $this->language) : null,
            'anchors' => $this->panel === 'anchors' ? $pages->anchors($this->chosenPageId ?? $this->pageId, $this->language) : [],
            'files' => $this->panel === 'files' ? $pages->files($this->pageId) : [],
            'chosenFiles' => $this->panel === 'files' && $hasOtherPage ? $pages->files($this->chosenPageId) : [],
            'chosenTitle' => $hasOtherPage ? Value::string(lynx()->pages->get((int) $this->chosenPageId)?->title) : '',
            'targets' => $this->targets(),
            'rels' => $this->rels(),
        ]);
    }

    private function choose(string $href, string $name): void
    {
        $this->href = $href;
        $this->text = trim($this->text) === '' ? $name : $this->text;
        [$this->panel, $this->pageQuery] = ['', ''];
    }

    private function answer(string $href): void
    {
        $this->dispatch('lynx-link-chosen', requestId: $this->requestId, href: $href, text: $this->text, title: trim($this->title), target: $this->target, rel: implode(' ', Value::strings($this->rel)));
        $this->dispatch('close-dialog-lynx-link');
    }

    /**
     * Pages whose title holds the words typed in the address box (anything not shaped like an address).
     *
     * @return list<array{id: int, title: string, url: string, canHaveChildren: bool}>
     */
    private function matches(): array
    {
        $words = trim($this->href);

        if (mb_strlen($words) < self::MIN_SEARCH || preg_match('~^([a-z][a-z0-9+.-]*:|/|#|\?|www\.)~i', $words) === 1) {
            return [];
        }

        return app(LinkPages::class)->search($words, $this->language);
    }

    /**
     * The relations of a rel among those offered, in the offered order.
     *
     * @return list<string>
     */
    private function relations(string $rel): array
    {
        $tokens = preg_split('/\s+/', strtolower(trim($rel))) ?: [];

        return array_values(array_intersect(array_map(strval(...), array_keys($this->rels())), $tokens));
    }

    /**
     * @return array<string, string>
     */
    private function targets(): array
    {
        return array_map(Value::string(...), Value::map(config('lynx-admin.links.targets', [])));
    }

    /**
     * Relations from config that the HTML cleaner keeps.
     *
     * @return array<string, string>
     */
    private function rels(): array
    {
        return array_intersect_key(array_map(Value::string(...), Value::map(config('lynx-admin.links.rels', []))), array_flip(LinkRelSanitizer::CHOICES));
    }
}
