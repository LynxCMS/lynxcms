<?php

declare(strict_types=1);

namespace LynxCms\Cache\Fragments;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use LynxCms\Cache\FragmentEntry;
use LynxCms\Cache\Pages\Generation;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Support\Value;

/**
 * The fragment cache (ADR-0064), like ProcessWire's markup cache: parts of a page kept until
 * what they depend on changes, for pages that are not stored whole. lynx()->cache()->remember()
 * and @lynxCache ... @endLynxCache. Guests share copies per language; signed in users always get
 * a fresh render.
 *
 * @api
 */
final class Fragments
{
    /** @var list<array{key: ?FragmentKey, html: ?string, revision: string}> open @lynxCache blocks */
    private array $open = [];

    public function __construct(
        private readonly Repository $cache,
        private readonly Generation $generation,
        private readonly LanguageContext $languages,
    ) {}

    /**
     * @param  Closure(): string  $render
     * @param  list<string>  $dependsOn  page:12, template:course; empty for any change
     * @param  list<string|int>  $vary
     */
    public function remember(string $name, Closure $render, ?int $ttl = null, array $dependsOn = [], array $vary = []): string
    {
        $key = $this->keyFor($name, ['ttl' => $ttl, 'dependsOn' => $dependsOn, 'vary' => $vary]);
        $stored = $key === null ? null : $this->stored($key);

        if ($stored !== null) {
            return $stored;
        }

        $revision = $this->generation->revision();
        $html = $render();
        $this->store($key, $html, $revision);

        return $html;
    }

    /**
     * @lynxCache: true when the block must render (nothing stored), false when finish() has it.
     *
     * @param  array<string, mixed>  $options  ttl, dependsOn, vary
     */
    public function begin(string $name, array $options = []): bool
    {
        $key = $this->keyFor($name, $options);
        $stored = $key === null ? null : $this->stored($key);
        $this->open[] = ['key' => $key, 'html' => $stored, 'revision' => $this->generation->revision()];

        if ($stored === null) {
            ob_start();
        }

        return $stored === null;
    }

    /** @endLynxCache: the block's HTML, stored when it was rendered. */
    public function finish(): string
    {
        $block = array_pop($this->open);

        if ($block === null) {
            return '';
        }

        if ($block['html'] !== null) {
            return $block['html'];
        }

        $html = (string) ob_get_clean();
        $this->store($block['key'], $html, $block['revision']);

        return $html;
    }

    /** What a listed fragment holds now, or null when it is gone from the store. */
    public function html(FragmentEntry $entry): ?string
    {
        $html = $this->cache->get($entry->cache_key);

        return is_string($html) ? $html : null;
    }

    /** @param array<string, mixed> $options */
    private function keyFor(string $name, array $options): ?FragmentKey
    {
        if (config('lynx-cache.fragments.enabled') !== true || auth()->check()) {
            return null;
        }

        $dependsOn = Value::strings($options['dependsOn'] ?? null);
        $ttl = Value::nullableInt($options['ttl'] ?? null) ?? Value::nullableInt(config('lynx-cache.fragments.ttl'));

        return new FragmentKey($name, $this->languages->current()->code, $this->audience(), array_map(fn (mixed $value): string => Value::string($value), Value::list($options['vary'] ?? null)), $dependsOn === [] ? [FragmentEntry::ANY] : $dependsOn, $ttl);
    }

    /** Guests, split by the cookies that change what a guest may see (unlocked pages). */
    private function audience(): string
    {
        $cookies = array_intersect_key($this->request()->cookies->all(), array_flip(Value::strings(config('lynx-cache.fragments.vary_cookies'))));
        ksort($cookies);

        return $cookies === [] ? 'guest' : 'guest:'.sha1(json_encode($cookies, JSON_THROW_ON_ERROR));
    }

    private function stored(FragmentKey $key): ?string
    {
        $html = $this->cache->get($key->cacheKey());

        return is_string($html) ? $html : null;
    }

    private function store(?FragmentKey $key, string $html, string $revision): void
    {
        if ($key === null || $this->generation->revision() !== $revision || $this->holdsCsrfToken($html)) {
            return;
        }

        $key->ttl === null ? $this->cache->forever($key->cacheKey(), $html) : $this->cache->put($key->cacheKey(), $html, $key->ttl);
        FragmentEntry::query()->updateOrCreate(['key_hash' => sha1($key->cacheKey())], [
            'cache_key' => $key->cacheKey(),
            'name' => $key->name,
            'language' => $key->language,
            'audience' => $key->audience,
            'vary' => json_encode($key->vary, JSON_THROW_ON_ERROR),
            'depends_on' => ','.implode(',', $key->dependsOn).',',
            'size' => strlen($html),
            'expires_at' => $key->ttl === null ? null : now()->addSeconds($key->ttl),
            'created_at' => now(),
        ]);
    }

    /** Read each time: one Fragments instance can outlive a request. */
    private function request(): Request
    {
        return app(Request::class);
    }

    /** The visitor's token is theirs alone: such a fragment is rendered each time. */
    private function holdsCsrfToken(string $html): bool
    {
        $session = app('session.store');
        $token = $session instanceof Session && $session->isStarted() ? Value::string($session->token()) : '';

        return $token !== '' && str_contains($html, $token);
    }
}
