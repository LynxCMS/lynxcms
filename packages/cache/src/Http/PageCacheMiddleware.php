<?php

declare(strict_types=1);

namespace LynxCms\Cache\Http;

use Closure;
use Illuminate\Http\Request;
use LynxCms\Cache\Pages\Eligibility;
use LynxCms\Cache\Pages\Generation;
use LynxCms\Cache\Pages\PageCacheServer;
use LynxCms\Cache\Pages\PageCacheWriter;
use LynxCms\Cache\Pages\PageRequest;
use LynxCms\Cache\Pages\TemplateRules;
use LynxCms\Core\Http\PageAttributes;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global middleware, first in the stack (ADR-0064): answers from the page cache before the
 * session starts, and otherwise notes the page so terminate() can store it once it was sent.
 *
 * @internal
 */
final readonly class PageCacheMiddleware
{
    /** Set while a user is signed in, so web servers send that user to Laravel. */
    private const string BYPASS_COOKIE = 'lynx_bypass';

    public function __construct(
        private PageCacheServer $server,
        private PageCacheWriter $writer,
        private Eligibility $eligibility,
        private Generation $generation,
        private TemplateRules $rules,
    ) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('lynx-cache.pages.enabled') !== true) {
            return $next($request);
        }

        $address = $this->server->addressFor($request);
        $hit = $address === null ? null : $this->server->respond($request, $address);

        if ($hit !== null) {
            return $hit;
        }

        $pending = $address === null ? null : new PageRequest($address, $this->generation->revision());

        if ($pending !== null) {
            $request->attributes->set(PageRequest::ATTRIBUTE, $pending);
        }

        $response = $next($request);
        $this->keepBypassCookie($request, $response);

        return $this->annotate($request, $response, $pending);
    }

    public function terminate(Request $request, Response $response): void
    {
        $pending = $request->attributes->get(PageRequest::ATTRIBUTE);

        if ($pending instanceof PageRequest) {
            $this->writer->store($request, $response, $pending);
        }
    }

    private function annotate(Request $request, Response $response, ?PageRequest $pending): Response
    {
        if ($request->attributes->get(PageAttributes::PAGE_ROUTE) !== true) {
            return $response;
        }

        $pending = $pending === null ? null : $this->withPage($request, $pending);
        $reason = $pending === null ? 'request' : $this->eligibility->skipReason($request, $response, $pending);

        if ($pending !== null) {
            $request->attributes->set(PageRequest::ATTRIBUTE, $pending);
        }

        $request->attributes->set(PageRequest::REASON, $reason);

        if (config('lynx-cache.pages.debug') === true) {
            $response->headers->set('X-Lynx-Cache', $reason === null ? 'MISS' : 'SKIP; reason='.$reason);
        }

        return $response;
    }

    private function withPage(Request $request, PageRequest $pending): PageRequest
    {
        $page = $request->attributes->get(PageAttributes::PAGE);
        $language = Value::string($request->attributes->get(PageAttributes::LANGUAGE));

        return $page instanceof Page ? $pending->withPage($page, $language, $this->rules->for($page->template()->name)) : $pending;
    }

    /**
     * Signed in users get the cookie on any response, the admin's included, so web servers stop
     * sending them stored pages; signed out visitors lose it and get stored pages again.
     */
    private function keepBypassCookie(Request $request, Response $response): void
    {
        $hasCookie = $request->cookies->has(self::BYPASS_COOKIE);

        if ($request->user() !== null && ! $hasCookie) {
            $response->headers->setCookie(Cookie::create(self::BYPASS_COOKIE, '1'));
        } elseif ($request->user() === null && $hasCookie) {
            $response->headers->clearCookie(self::BYPASS_COOKIE);
        }
    }
}
