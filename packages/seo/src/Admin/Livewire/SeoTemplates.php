<?php

declare(strict_types=1);

namespace LynxCms\Seo\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Schema\SchemaEditing;
use LynxCms\Seo\Actions\DisableSeo;
use LynxCms\Seo\Actions\EnableSeo;
use LynxCms\Seo\SeoSchema;

/**
 * The SEO screen's first tab: every template with URLs, with SEO fields on or off. Turning them
 * on or off changes a template's fields, so where schema editing is off (ADR-0117) it is read only.
 *
 * @internal
 */
final class SeoTemplates extends Component
{
    use Notifies;

    /** The last notice, for screen readers and tests (Notifies). */
    #[Locked]
    public ?string $status = null;

    public function boot(AdminAccess $access): void
    {
        $access->authorizeScreen('seo.manage');
    }

    /** Turns SEO on when the template lacks the fields, off when it has them. It applies at once, so a notice says so. */
    public function toggle(string $template): void
    {
        abort_unless(app(SchemaEditing::class)->isEnabled(), 403);
        $isOn = $this->hasSeo($template);
        lynx()->run($isOn ? DisableSeo::class : EnableSeo::class, ['template' => $template]);
        $this->notify(__($isOn ? 'lynx-seo::admin.templates.turned_off' : 'lynx-seo::admin.templates.turned_on', ['template' => $template]));
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
            'canChange' => app(SchemaEditing::class)->isEnabled(),
        ]);
    }

    private function hasSeo(string $template): bool
    {
        return lynx()->templates->fields($template)->has(SeoSchema::TITLE);
    }
}
