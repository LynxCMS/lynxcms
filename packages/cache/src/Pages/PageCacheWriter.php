<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

use Illuminate\Http\Request;
use LynxCms\Cache\PageCacheEntry;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Support\Value;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Stores a page after its response was sent, once the final response passes Eligibility (other
 * code may still change it after the middleware, e.g. in RequestHandled listeners).
 *
 * @internal
 */
final readonly class PageCacheWriter
{
    public function __construct(
        private TokenRefresh $tokenRefresh,
        private Eligibility $eligibility,
        private Generation $generation,
        private PageStore $store,
        private LanguageRegistry $languages,
    ) {}

    public function store(Request $request, Response $response, PageRequest $pending): void
    {
        if (! $this->mayStore($request, $response, $pending)) {
            return;
        }

        try {
            $this->write($request, $response, $pending);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function mayStore(Request $request, Response $response, PageRequest $pending): bool
    {
        return $this->eligibility->skipReason($request, $response, $pending) === null
            // A clear ran while the page rendered: it may show content from before the change.
            && $this->generation->revision() === $pending->revision
            && ! $this->hasTooManyVariants($pending->address);
    }

    private function hasTooManyVariants(CacheAddress $address): bool
    {
        return $address->query !== '' && PageCacheEntry::query()
            ->where('host', $address->host)
            ->where('path', $address->path)
            ->where('query', '!=', '')
            ->count() >= Value::int(config('lynx-cache.pages.max_variants'));
    }

    private function write(Request $request, Response $response, PageRequest $pending): void
    {
        assert($pending->page !== null && $pending->page->id !== null && $pending->language !== null && $pending->rule !== null);
        $now = now()->getTimestamp();
        $expiresAt = $pending->rule->lifetime > 0 ? $now + $pending->rule->lifetime : null;
        $address = $pending->address;

        $html = $this->tokenRefresh->prepare((string) $response->getContent(), $request->hasSession() ? Value::string($request->session()->token()) : '');
        $this->store->put($address, new CachedPage($html, $now, $expiresAt));
        PageCacheEntry::query()->updateOrCreate(['key_hash' => sha1($address->key())], [
            'host' => $address->host,
            'path' => $address->path,
            'query' => $address->query,
            'page_id' => $pending->page->id,
            'parent_id' => $pending->page->parentId,
            'ancestor_ids' => implode(',', Ancestors::idsOf($pending->page)),
            'language_id' => $this->languages->get($pending->language)->id,
            'template' => $pending->page->template()->name,
            'expires_at' => $expiresAt === null ? null : date('Y-m-d H:i:s', $expiresAt),
            'created_at' => date('Y-m-d H:i:s', $now),
        ]);
    }
}
