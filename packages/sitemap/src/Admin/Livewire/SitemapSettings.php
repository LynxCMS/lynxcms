<?php

declare(strict_types=1);

namespace LynxCms\Sitemap\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Fields\Choices\Choice;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Value;
use LynxCms\Sitemap\Actions\ClearSitemapCache;
use LynxCms\Sitemap\Actions\InstallSitemap;
use LynxCms\Sitemap\Sitemap;
use LynxCms\Sitemap\SitemapSchema;

/**
 * The sitemap screen: URLs per file, grouping and templates left out, saved on the settings page
 * through pages.save, with the parts the sitemap has now.
 *
 * @internal
 */
final class SitemapSettings extends Component
{
    /** Empty uses lynx-sitemap.per_file. */
    public string $perFile = '';

    public string $groupBy = '';

    /** @var list<string> */
    public array $exclude = [];

    /** What the last action did, shown beside the buttons. */
    public string $status = '';

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('sitemap.manage'), 403);
    }

    public function mount(): void
    {
        $settings = lynx()->settings(SitemapSchema::SETTINGS_TEMPLATE);
        $perFile = $settings?->templateField(SitemapSchema::PER_FILE) === null ? null : $settings->getUnformatted(SitemapSchema::PER_FILE);
        $groupBy = $settings?->templateField(SitemapSchema::GROUP_BY) === null ? null : $settings->get(SitemapSchema::GROUP_BY);
        $exclude = $settings?->templateField(SitemapSchema::EXCLUDE) === null ? '' : Value::string($settings->getUnformatted(SitemapSchema::EXCLUDE));

        $this->perFile = $perFile === null ? '' : (string) Value::int($perFile);
        $this->groupBy = $groupBy instanceof Choice ? $groupBy->value : '';
        $this->exclude = array_values(array_filter(array_map(trim(...), explode(',', $exclude))));
    }

    public function save(): void
    {
        $this->validate([
            'perFile' => ['nullable', 'integer', 'min:1', 'max:'.SitemapSchema::MAX_PER_FILE],
            'groupBy' => ['nullable', Rule::in(['', SitemapSchema::GROUP_NONE, SitemapSchema::GROUP_TEMPLATE])],
            'exclude.*' => ['string', Rule::in($this->templates()->keys()->all())],
        ]);

        lynx()->run(InstallSitemap::class);
        lynx()->run(SavePage::class, ['id' => lynx()->settings(SitemapSchema::SETTINGS_TEMPLATE)?->id, 'values' => [
            SitemapSchema::PER_FILE => $this->perFile === '' ? null : (int) $this->perFile,
            SitemapSchema::GROUP_BY => $this->groupBy === '' ? null : $this->groupBy,
            SitemapSchema::EXCLUDE => implode(', ', $this->exclude),
        ]]);
        $this->status = 'Saved.';
    }

    public function clearCache(): void
    {
        lynx()->run(ClearSitemapCache::class);
        $this->status = 'Cache cleared: the next visit builds the sitemap again.';
    }

    public function render(): View
    {
        $sitemap = app(Sitemap::class);

        return view('lynx-sitemap::admin.livewire.settings', [
            'templates' => $this->templates()->all(),
            'parts' => $sitemap->parts(),
            'pages' => $sitemap->pageCount(),
            'configPerFile' => Value::int(config('lynx-sitemap.per_file'), 10000),
            'max' => SitemapSchema::MAX_PER_FILE,
        ]);
    }

    /** @return Collection<string, string> templates with URLs: name => label */
    private function templates(): Collection
    {
        return lynx()->templates->all()->filter(fn (Template $template): bool => $template->hasUrl())->sortBy('name')
            ->mapWithKeys(fn (Template $template): array => [$template->name => $template->labels()->in()]);
    }
}
