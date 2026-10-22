<?php

declare(strict_types=1);

namespace LynxCms\PagePassword\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Support\Value;
use LynxCms\PagePassword\Access\ProtectedRoots;
use LynxCms\PagePassword\Actions\DisablePagePassword;
use LynxCms\PagePassword\Actions\EnablePagePassword;
use LynxCms\PagePassword\PagePasswordSchema;

/**
 * Settings > Page passwords: which templates' pages may have a password, and the pages that
 * have one, with how many pages below each share it.
 *
 * @internal
 */
final class PagePasswordSettings extends Component
{
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('schema.manage'), 403);
    }

    public function enable(string $template): void
    {
        lynx()->run(EnablePagePassword::class, ['template' => $template]);
    }

    public function disable(string $template): void
    {
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
