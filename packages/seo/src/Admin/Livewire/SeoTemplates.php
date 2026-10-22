<?php

declare(strict_types=1);

namespace LynxCms\Seo\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Core\Models\Template;
use LynxCms\Seo\Actions\DisableSeo;
use LynxCms\Seo\Actions\EnableSeo;
use LynxCms\Seo\SeoSchema;

/**
 * The SEO screen's first tab: every template with URLs, with SEO fields on or off.
 *
 * @internal
 */
final class SeoTemplates extends Component
{
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('seo.manage'), 403);
    }

    /** Turns SEO on when the template lacks the fields, off when it has them. */
    public function toggle(string $template): void
    {
        $action = $this->hasSeo($template) ? DisableSeo::class : EnableSeo::class;
        lynx()->run($action, ['template' => $template]);
    }

    public function render(): View
    {
        $templates = lynx()->templates->all()->filter(fn (Template $template): bool => $template->hasUrl())->sortBy('name');

        return view('lynx-seo::admin.livewire.templates', [
            'rows' => $templates->map(fn (Template $template): array => [
                'name' => $template->name,
                'label' => $template->labels()->in(),
                'pages' => lynx()->pages->count('template='.$template->name.', include=all'),
                'isOn' => $this->hasSeo($template->name),
            ])->values()->all(),
        ]);
    }

    private function hasSeo(string $template): bool
    {
        return lynx()->templates->fields($template)->has(SeoSchema::TITLE);
    }
}
