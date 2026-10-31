<?php

declare(strict_types=1);

namespace LynxCms\Cache\Admin\Livewire;

use Carbon\CarbonInterval;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Cache\Actions\ClearPageCache;
use LynxCms\Cache\Actions\SavePageCacheRules;
use LynxCms\Cache\PageCacheEntry;
use LynxCms\Cache\PageCacheSchema;
use LynxCms\Cache\Pages\PageCacheSettings;
use LynxCms\Cache\Pages\ServerRules;
use LynxCms\Cache\Pages\TemplateRule;
use LynxCms\Cache\Pages\TemplateRules;
use LynxCms\Cache\Pages\TokenRefresh;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Value;

/**
 * The page cache screen: template rules saved through page_cache.save_rules, what is stored per
 * template, clearing, and the rules that let a web server send stored pages itself.
 *
 * @internal
 */
final class PageCacheScreen extends Component
{
    /** Clearing scopes with words in the language file. */
    private const array SCOPES = ['self', 'parents', 'children', 'siblings', 'home'];

    /** @var array<string, array{cache: bool, lifetime: string, parameters: string, clear: list<string>, csrf: bool}> */
    public array $rules = [];

    /** Empty uses lynx-cache.pages.lifetime. */
    public string $lifetime = '';

    public string $server = 'nginx';

    /** A page the editor linked to, to show and clear on its own. */
    #[Url]
    public ?int $page = null;

    /** What the last action did, shown beside the button that did it. */
    public string $status = '';

    /** Where the status shows: "stored" (clearing) or "rules" (saving). */
    public string $statusFor = '';

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('page_cache.manage'), 403);
    }

    public function mount(TemplateRules $rules): void
    {
        $all = $rules->all();
        $own = lynx()->settings(PageCacheSchema::SETTINGS_TEMPLATE)?->getUnformatted(PageCacheSchema::LIFETIME);
        $this->lifetime = $own === null || $own === '' ? '' : (string) Value::int($own);

        foreach ($this->templates()->keys() as $name) {
            $this->rules[$name] = $this->row($all[$name] ?? $all['*'] ?? null);
        }
    }

    public function save(): void
    {
        $templates = [];

        foreach ($this->rules as $name => $row) {
            $templates[$name] = [
                'cache' => $row['cache'],
                'lifetime' => $row['lifetime'] === '' ? null : $row['lifetime'],
                'parameters' => array_values(array_filter(array_map(trim(...), explode(',', $row['parameters'])))),
                'clear' => $row['clear'],
                'csrf' => $row['csrf'] ? TokenRefresh::REFRESH : 'skip',
            ];
        }

        lynx()->run(SavePageCacheRules::class, ['lifetime' => $this->lifetime === '' ? null : $this->lifetime, 'templates' => $templates]);
        $this->report('rules', Value::string(__('lynx-cache::admin.status.rules_saved')));
    }

    public function clearAll(): void
    {
        lynx()->run(ClearPageCache::class);
        $this->report('stored', Value::string(__('lynx-cache::admin.status.all_cleared')));
    }

    public function clearTemplate(string $template): void
    {
        lynx()->run(ClearPageCache::class, ['template' => $template]);
        $this->report('stored', Value::string(__('lynx-cache::admin.status.template_cleared', ['template' => $template])));
    }

    public function clearPage(): void
    {
        if ($this->page !== null) {
            lynx()->run(ClearPageCache::class, ['pages' => [$this->page]]);
            $this->report('stored', Value::string(__('lynx-cache::admin.status.page_cleared')));
        }
    }

    public function render(ServerRules $serverRules, PageCacheSettings $settings): View
    {
        $counts = PageCacheEntry::query()->selectRaw('template, count(*) as pages')->groupBy('template')->pluck('pages', 'template');

        return view('lynx-cache::admin.livewire.screen', [
            'templates' => $this->templates()->all(),
            'counts' => $counts->map(fn (mixed $count): int => Value::int($count))->all(),
            'total' => PageCacheEntry::query()->count(),
            'pageEntries' => $this->page === null ? collect() : PageCacheEntry::query()->where('page_id', $this->page)->get(),
            'isEnabled' => config('lynx-cache.pages.enabled') === true,
            'driver' => Value::string(config('lynx-cache.pages.driver')),
            'serverText' => $serverRules->for($this->server) ?? '',
            'servers' => ServerRules::SERVERS,
            // Ticking nothing already means every page, so "all" is left out here.
            'scopes' => array_values(array_diff(PageCacheSchema::SCOPES, [TemplateRule::CLEAR_ALL])),
            'hosts' => $settings->hosts(),
            'configLifetime' => Value::int(config('lynx-cache.pages.lifetime')),
            'summaries' => array_map($this->summary(...), $this->rules),
        ]);
    }

    /**
     * A template's rule in a line, for its row on the Rules tab: "Cached · 2 minutes · a change clears every page".
     *
     * @param  array{cache: bool, lifetime: string, parameters: string, clear: list<string>, csrf: bool}  $row
     */
    private function summary(array $row): string
    {
        if (! $row['cache']) {
            return Value::string(__('lynx-cache::admin.summary.not_cached'));
        }

        $kept = match ($row['lifetime']) {
            '' => Value::string(__('lynx-cache::admin.summary.default')),
            '0' => Value::string(__('lynx-cache::admin.summary.until_change')),
            default => Value::string(__('lynx-cache::admin.summary.for', ['time' => CarbonInterval::seconds(Value::int($row['lifetime']))->cascade()->forHumans()])),
        };
        $cleared = array_map(fn (string $scope): string => lcfirst(self::scopeWords($scope)), $row['clear']);

        return Value::string(__('lynx-cache::admin.summary.clears', ['kept' => $kept, 'what' => $cleared === [] ? __('lynx-cache::admin.summary.every_page') : Arr::join($cleared, ', ', Value::string(__('lynx-cache::admin.summary.and')))]));
    }

    /** What a clearing scope reaches, in words, from the changed page. */
    public static function scopeWords(string $scope): string
    {
        return in_array($scope, self::SCOPES, true) ? Value::string(__('lynx-cache::admin.scopes.'.$scope)) : $scope;
    }

    private function report(string $for, string $status): void
    {
        $this->statusFor = $for;
        $this->status = $status;
    }

    /** @return array{cache: bool, lifetime: string, parameters: string, clear: list<string>, csrf: bool} */
    private function row(mixed $rule): array
    {
        $rule = $rule === false || $rule === null ? null : Value::map($rule);
        $lifetime = $rule['lifetime'] ?? null;

        return [
            'cache' => $rule !== null,
            'lifetime' => $lifetime === null ? '' : (string) Value::int($lifetime),
            'parameters' => implode(', ', Value::strings($rule['parameters'] ?? null)),
            'clear' => Value::strings($rule['clear'] ?? null),
            'csrf' => Value::string($rule['csrf'] ?? config('lynx-cache.pages.csrf')) === TokenRefresh::REFRESH,
        ];
    }

    /** @return Collection<string, string> templates with URLs: name => label */
    private function templates(): Collection
    {
        return lynx()->templates->all()->filter(fn (Template $template): bool => $template->hasUrl())->sortBy('name')
            ->mapWithKeys(fn (Template $template): array => [$template->name => $template->labels()->in()]);
    }
}
