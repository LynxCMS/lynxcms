<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

use Illuminate\Http\Request;
use LynxCms\Core\Support\Value;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Answers a request from the page cache before Laravel starts a session or routes it.
 *
 * @internal
 */
final readonly class PageCacheServer
{
    public function __construct(private RequestRules $rules, private PageStore $store) {}

    public function addressFor(Request $request): ?CacheAddress
    {
        return $this->rules->addressFor(
            $request->getMethod(),
            $request->getHost(),
            $request->getPathInfo(),
            Value::string($request->server->get('QUERY_STRING')),
            $request->cookies->all(),
        );
    }

    /** The stored page as a response, or null. A store that fails is a miss, never an error page. */
    public function respond(Request $request, CacheAddress $address): ?Response
    {
        try {
            $page = $this->store->get($address);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        return $page === null ? null : $this->responseFor($request, $page);
    }

    private function responseFor(Request $request, CachedPage $page): Response
    {
        $response = new Response($page->html, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
        $edgeTtl = Value::int(config('lynx-cache.pages.edge_ttl'));

        if ($edgeTtl > 0) {
            $response->setPublic()->setMaxAge(0)->setSharedMaxAge($edgeTtl);
        }

        if (config('lynx-cache.pages.debug') === true) {
            $response->headers->set('X-Lynx-Cache', 'HIT');
            $response->headers->set('X-Lynx-Cache-Age', (string) max(0, now()->getTimestamp() - $page->createdAt));
        }

        return $response->prepare($request);
    }
}
