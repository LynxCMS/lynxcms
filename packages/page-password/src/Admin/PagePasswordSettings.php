<?php

declare(strict_types=1);

namespace LynxCms\PagePassword\Admin;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Schema\SchemaEditing;
use LynxCms\Core\Support\Value;
use LynxCms\PagePassword\Access\ProtectedRoots;
use LynxCms\PagePassword\Actions\DisablePagePassword;
use LynxCms\PagePassword\Actions\EnablePagePassword;
use LynxCms\PagePassword\PagePasswordSchema;

/**
 * Settings > Page passwords: which templates' pages may have a password, and the pages that
 * have one, with how many pages below each share it. Turning passwords on or off changes a
 * template's fields, so where schema editing is off (ADR-0117) the templates are read only.
 *
 * @internal
 */
final class PagePasswordSettings extends Component
{
    public function boot(AdminAccess $access): void
    {
        $access->authorizeScreen('schema.manage');
    }

    public function enable(string $template): void
    {
        abort_unless(app(SchemaEditing::class)->isEnabled(), 403);
        lynx()->run(EnablePagePassword::class, ['template' => $template]);
    }

    public function disable(string $template): void
    {
        abort_unless(app(SchemaEditing::class)->isEnabled(), 403);
        lynx()->run(DisablePagePassword::class, ['template' => $template]);
    }

    public function render(): View
    {
        app(ProtectedRoots::class)->flush();

        return view('lynx-page-password::admin.settings', [
            'templates' => lynx()->templates->all()->filter(fn (Template $template): bool => $template->hasUrl())
                ->map(fn (Template $template): array => ['name' => (string) $template->name, 'label' => $template->label, 'isOn' => lynx()->templates->fields($template)->has(PagePasswordSchema::FIELD)])
                ->values()->all(),
            'pages' => $this->protectedPages(),
            'canChange' => app(SchemaEditing::class)->isEnabled(),
        ]);
    }

    /** @return list<array{id: int, title: string, url: string, below: int}> */
    private function protectedPages(): array
    {
        $roots = app(ProtectedRoots::class);
        $below = $roots->pagesBelow();
        $pages = [];

        foreach (app(PageLoader::class)->many($roots->ids()) as $page) {
            if ($page instanceof Page) {
                $pages[] = ['id' => (int) $page->id, 'title' => Value::string($page->getUnformatted('title')), 'url' => $page->url(), 'below' => $below[(int) $page->id] ?? 0];
            }
        }

        return $pages;
    }
}
