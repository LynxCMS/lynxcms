<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\Request;
use Livewire\LivewireManager;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageUrls;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\Core\Support\Value;

/**
 * Fills the page cache before visitors arrive (ADR-0064): every published page with a URL whose
 * template has a cache rule, in every language it is live in, rendered through the HTTP kernel so
 * the same rules decide what is stored.
 *
 * @internal
 */
final readonly class Warmer
{
    /** Pages loaded per query. */
    private const int CHUNK = 200;

    public function __construct(private Kernel $kernel, private CookieJar $cookies, private TemplateRules $rules, private PageStore $store, private RequestRules $requestRules, private PageVisibility $visibility, private PageUrls $urls) {}

    /** @return array<string, int> stored => n, and skip reason => n */
    public function warm(): array
    {
        $report = [];
        $selector = $this->templateSelector();

        for ($start = 0; $selector !== null; $start += self::CHUNK) {
            $pages = lynx()->pages->find("template={$selector}, include=hidden, sort=id, start={$start}, limit=".self::CHUNK);

            foreach ($pages as $page) {
                foreach ($this->urlsOf($page) as $url) {
                    $outcome = $this->visit($url);
                    $report[$outcome] = ($report[$outcome] ?? 0) + 1;
                }
            }

            if ($pages->count() < self::CHUNK) {
                break;
            }
        }

        return $report;
    }

    /** Templates with URLs and a cache rule, as a selector value; null when there are none. */
    private function templateSelector(): ?string
    {
        $names = lynx()->templates->all()
            ->filter(fn (Template $template): bool => $template->hasUrl() && $this->rules->for($template->name) !== null)
            ->map(fn (Template $template): string => $template->name)
            ->values()->all();

        return $names === [] ? null : implode('|', $names);
    }

    /** @return list<string> */
    private function urlsOf(Page $page): array
    {
        $root = rtrim(Value::string(config('app.url')), '/');

        return array_map(fn (string $url): string => $root.$url, $this->urls->of($page));
    }

    /** "stored", or why the page was not stored. */
    private function visit(string $url): string
    {
        // Visits share one process: cookies queued by the last one (the cookie session driver
        // queues its data) would otherwise be sent with this one and make it look personal.
        $this->cookies->flushQueuedCookies();
        // Livewire keeps per request flags (scripts printed, components rendered) until reset,
        // as Octane does between requests; without this only the first page gets its script.
        if (class_exists(LivewireManager::class) && app()->bound(LivewireManager::class)) {
            app(LivewireManager::class)->flushState();
        }

        // A console process has visibility rules off; visitors do not, and stored pages are for visitors.
        $request = Request::create($url);
        $this->visibility->asVisitor(function () use ($request): void {
            $this->kernel->terminate($request, $this->kernel->handle($request));
        });
        $address = $this->requestRules->addressFor('GET', $request->getHost(), $request->getPathInfo(), '', []);

        if ($address !== null && $this->store->get($address) !== null) {
            return 'stored';
        }

        return Value::string($request->attributes->get(PageRequest::REASON), 'not stored');
    }
}
