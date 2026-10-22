<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

use Illuminate\Http\Request;
use LynxCms\Core\Support\Value;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Whether a rendered page may be stored, as a reason it may not (ADR-0064). Only what any guest
 * would see qualifies: a 200 HTML page at its canonical address, with nothing private, personal
 * or tied to the visitor's session in it.
 *
 * @internal
 */
final class Eligibility
{
    /** Request attribute templates set (@nocache) to keep a page out of the cache. */
    public const string OPT_OUT = 'lynx-cache.skip';

    /** Cookies every response carries; any other cookie makes the response personal. */
    private const string XSRF_COOKIE = 'XSRF-TOKEN';

    /** The Expires header Livewire's DisableBackButtonCacheMiddleware sends. */
    private const string LIVEWIRE_EXPIRES = 'Fri, 01 Jan 1990 00:00:00 GMT';

    public function skipReason(Request $request, Response $response, PageRequest $pending): ?string
    {
        return match (true) {
            $response->getStatusCode() !== Response::HTTP_OK => 'status',
            $request->user() !== null => 'signed-in',
            $pending->page === null || $pending->language === null => 'not-a-page',
            $pending->rule === null => 'template',
            ! $pending->rule->allowsQuery($pending->address->query) => 'query',
            ! $this->isCanonical($request, $pending) => 'path',
            default => $this->responseReason($request, $response, $pending->rule),
        };
    }

    /** Case and characters must match the page's URL; a trailing slash may differ, as web servers ignore it. */
    private function isCanonical(Request $request, PageRequest $pending): bool
    {
        assert($pending->page !== null && $pending->language !== null);

        return trim(rawurldecode($request->getPathInfo()), '/') === trim($pending->page->localUrl($pending->language), '/');
    }

    private function responseReason(Request $request, Response $response, TemplateRule $rule): ?string
    {
        return match (true) {
            ! str_starts_with(Value::string($response->headers->get('Content-Type')), 'text/html') => 'type',
            $this->isMarkedPrivate($response) => 'private',
            $this->hasPersonalCookies($request, $response) => 'cookie',
            ! $rule->refreshesToken && $this->holdsCsrfToken($request, $response) => 'csrf-token',
            $request->attributes->get(self::OPT_OUT) === true => 'opted-out',
            default => null,
        };
    }

    /** Symfony calls every response "private" unless told otherwise; no-store is the signal. */
    private function isMarkedPrivate(Response $response): bool
    {
        return $response->headers->hasCacheControlDirective('no-store') && ! $this->isLivewireBackButtonGuard($response);
    }

    /**
     * Livewire marks every page with a component no-store, so the back button shows the live
     * component rather than a stale copy: that is about the browser, not about who may see the
     * page. Only its exact headers count, so anything else marked no-store stays private.
     */
    private function isLivewireBackButtonGuard(Response $response): bool
    {
        return $response->headers->get('Expires') === self::LIVEWIRE_EXPIRES
            && $response->headers->get('Pragma') === 'no-cache'
            && $response->headers->hasCacheControlDirective('must-revalidate');
    }

    private function hasPersonalCookies(Request $request, Response $response): bool
    {
        // The cookie session driver keeps the session's data in a cookie named after its id.
        $sessionData = $request->hasSession() ? $request->session()->getId() : '';
        $shared = [Value::string(config('session.cookie')), self::XSRF_COOKIE, $sessionData];

        return array_any($response->headers->getCookies(), fn (Cookie $cookie): bool => ! in_array($cookie->getName(), $shared, true));
    }

    /** A form or Livewire component carries the visitor's token, which other visitors cannot use. */
    private function holdsCsrfToken(Request $request, Response $response): bool
    {
        $token = $request->hasSession() ? Value::string($request->session()->token()) : '';

        return $token !== '' && str_contains((string) $response->getContent(), $token);
    }
}
