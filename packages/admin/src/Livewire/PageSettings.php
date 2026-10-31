<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminBranches;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Editor\TemplateChoices;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Admin\Tree\DefaultPresenter;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Pages\SetPageStatus;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageName;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Pages\PageUrl;
use LynxCms\Core\Support\Value;

/**
 * The editor's Settings tab: URL names per language and whether the page is published in each,
 * status flags, template, and parent. Every change goes through the page actions, then tells the
 * editor ("lynx-page-changed") so its content form stays in step with the page's revision.
 *
 * @api
 */
final class PageSettings extends Component
{
    use Notifies;

    #[Locked]
    public int $pageId;

    #[Locked]
    public int $revision = 0;

    /** @var array<string, string> language code => URL name */
    public array $names = [];

    /** @var array<string, bool> language code => published in that language */
    public array $active = [];

    public string $template = '';

    /** @var list<string>|null fields a previewed template switch would orphan; null when not previewed */
    #[Locked]
    public ?array $orphans = null;

    /** @var list<int> the new parent chosen in the page picker */
    public array $parent = [];

    #[Locked]
    public ?string $status = null;

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
    }

    public function mount(int $pageId): void
    {
        $this->pageId = $pageId;
        $this->load();
    }

    public function saveNames(): void
    {
        lynx()->run(SavePage::class, ['id' => $this->pageId, 'names' => $this->namesInput(), 'expected_revision' => $this->revision]);
        $this->changed(__('lynx-admin::settings.names_saved'));
    }

    public function toggleStatus(string $flag): void
    {
        if (! in_array($flag, SetPageStatus::EDITABLE, true)) {
            return;
        }

        $isSet = $this->page()->hasStatus(PageStatus::NAMES[$flag]);
        lynx()->pages->setStatus($this->pageId, ...($isSet ? ['remove' => [$flag]] : ['add' => [$flag]]));
        $this->changed(__('lynx-admin::settings.status.saved'));
    }

    /** A dry run of the switch, so the editor sees which fields' values would be orphaned first. */
    public function previewTemplate(): void
    {
        $result = lynx()->pages->switchTemplate($this->pageId, $this->template, dryRun: true);
        $orphaned = $result instanceof ActionResult ? Value::array($result->arrayValue()['orphaned'] ?? []) : [];
        $this->orphans = Value::strings($orphaned);
    }

    public function switchTemplate(): void
    {
        if ($this->orphans === null) {
            $this->previewTemplate();

            return;
        }

        lynx()->pages->switchTemplate($this->pageId, $this->template);
        $this->changed(__('lynx-admin::settings.template.switched'), fieldsChanged: true);
    }

    public function cancelTemplate(): void
    {
        $this->orphans = null;
        $this->template = (string) $this->page()->template()->name;
    }

    public function move(): void
    {
        $parent = Value::ints($this->parent)[0] ?? null;

        if ($parent === null) {
            throw ValidationException::withMessages(['parent' => __('lynx-admin::settings.parent.choose_first')]);
        }

        lynx()->pages->move($this->pageId, $parent);
        $this->changed(__('lynx-admin::settings.parent.moved'));
    }

    public function render(TemplateChoices $choices): View
    {
        $page = $this->page();

        return view('lynx-admin::livewire.page-settings', [
            'page' => $page,
            'languages' => lynx()->languages->all(),
            'defaultCode' => Value::string(lynx()->languages->default()->code),
            'urls' => $this->previewUrls($page),
            'templates' => $this->templateChoices($page, $choices),
            'parentTitle' => $page->parent() instanceof Page ? (new DefaultPresenter)->title($page->parent()) : null,
            // Without a URL a page shows wherever a template puts it, in every language: one name, no languages to publish in.
            'hasUrl' => $page->template()->hasUrl(),
            'isModulePage' => app(AdminBranches::class)->contains($page),
            ...$this->permissions($page),
        ]);
    }

    private function load(): void
    {
        $page = $this->page();
        $this->revision = $page->revision;
        $this->template = (string) $page->template()->name;
        $this->orphans = null;
        $this->parent = [];

        foreach (lynx()->languages->all() as $language) {
            $code = Value::string($language->code);
            $this->names[$code] = Value::string($page->rawNames()[$language->id]['name'] ?? '');
            $this->active[$code] = $language->isDefault() || $page->isActiveIn($language);
        }
    }

    private function changed(string $message, bool $fieldsChanged = false): void
    {
        $this->load();
        $this->resetErrorBag();
        $this->notify($message);
        $this->dispatch('lynx-page-changed', revision: $this->revision, fieldsChanged: $fieldsChanged);
    }

    /**
     * @return array<string, array{name?: string, active: bool}>
     */
    private function namesInput(): array
    {
        $input = [];

        foreach (lynx()->languages->all() as $language) {
            $code = Value::string($language->code);
            $name = trim(Value::string($this->names[$code] ?? ''));
            // A language without its own name uses the default one (core falls back to it).
            $input[$code] = array_filter(['name' => $name === '' ? null : $name, 'active' => (bool) ($this->active[$code] ?? true)], fn (mixed $v): bool => $v !== null);
        }

        return $input;
    }

    /**
     * The URL each language will have, using the rule pages.save applies to names.
     *
     * @return array<string, string|null>
     */
    private function previewUrls(Page $page): array
    {
        $parent = $page->parent();
        $urls = [];

        foreach (lynx()->languages->all() as $language) {
            $code = Value::string($language->code);
            $name = PageName::from($this->names[$code] ?? '') ?: PageName::from($this->names[lynx()->languages->default()->code] ?? '');
            $urls[$code] = $parent instanceof Page && $page->template()->hasUrl() && $name !== ''
                ? PageUrl::child($parent->localUrl($language), $name)
                : null;
        }

        return $urls;
    }

    /**
     * @return array<string, string> template name => label, without the current template
     */
    private function templateChoices(Page $page, TemplateChoices $choices): array
    {
        $allowed = $page->template()->allowedTemplates();
        $offered = $choices->for($page->parent());
        $all = $allowed === [] ? $offered : array_intersect_key($offered, array_flip($allowed));
        unset($all[(string) $page->template()->name]);

        return $all;
    }

    /**
     * Which sections the current user may change (ADR-0033): the structural rules (home page,
     * system and locked pages) and the user's permissions on this page.
     *
     * @return array{can: array<string, bool>, canMove: bool, canSwitch: bool}
     */
    private function permissions(Page $page): array
    {
        $can = app(AdminAccess::class)->onPage($page);
        $isMovable = $page->parentId !== null && ! $page->hasStatus(PageStatus::SYSTEM) && ! $page->hasStatus(PageStatus::LOCKED);

        return ['can' => $can, 'canMove' => $isMovable && $can['move'], 'canSwitch' => $isMovable && $can['template']];
    }

    private function page(): Page
    {
        $page = lynx()->pages->get($this->pageId);

        return $page instanceof Page ? $page : abort(404);
    }
}
