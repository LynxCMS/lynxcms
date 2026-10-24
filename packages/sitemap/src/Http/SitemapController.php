<?php

declare(strict_types=1);

namespace LynxCms\Sitemap\Http;

use Illuminate\Http\Request;
use LynxCms\Sitemap\Sitemap;
use LynxCms\Sitemap\SitemapCache;
use LynxCms\Sitemap\SitemapPages;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /sitemap.xml and its parts, /sitemap-{n}.xml or /sitemap-{template}-{n}.xml, with an ETag so
 * crawlers that have the current file get a 304.
 *
 * @internal
 */
final readonly class SitemapController
{
    public function __construct(private Sitemap $sitemap, private SitemapCache $cache) {}

    public function root(Request $request): Response
    {
        return $this->respond($request, $this->sitemap->root());
    }

    public function part(Request $request, string $part): Response
    {
        return $this->respond($request, $this->sitemap->part(SitemapPages::ALL, (int) $part));
    }

    public function templatePart(Request $request, string $template, string $part): Response
    {
        return $this->respond($request, $this->sitemap->part($template, (int) $part));
    }

    private function respond(Request $request, ?string $body): Response
    {
        abort_if($body === null || ! (bool) config('lynx-sitemap.enabled', true), 404);

        $response = response($body)->header('Content-Type', 'application/xml; charset=UTF-8');

        if (! $this->cache->isShared()) {
            // Built for this visitor alone: no shared cache may keep it.
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        $response->setEtag(md5($body));
        $response->isNotModified($request);

        return $response;
    }
}
