<?php

declare(strict_types=1);

namespace LynxCms\Core\Doctor\Checks;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use LynxCms\Core\Doctor\Check;
use LynxCms\Core\Doctor\Finding;
use LynxCms\Core\Doctor\Severity;
use LynxCms\Core\Pages\ActivePageUrls;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Support\Value;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Pages visitors never reach, because a route of the app or a module answers their URL first:
 * pages are served by the fallback route, so a page at /lynx is hidden behind the admin's routes
 * on a site about lynxes (ADR-0122).
 *
 * @internal
 */
final readonly class PagesBehindRoutes implements Check
{
    public function __construct(private ActivePageUrls $urls, private Router $router, private PageLoader $pages) {}

    public function key(): string
    {
        return 'page-routes';
    }

    public function label(): string
    {
        return 'No route hides a page';
    }

    public function run(): array
    {
        $findings = [];

        foreach ($this->urls->all() as $url => $pageId) {
            $route = $this->routeFor($url);

            if ($route !== null) {
                $findings[] = $this->finding($url, $pageId, $route);
            }
        }

        return $findings;
    }

    /** The route that answers $url instead of the page, if any. */
    private function routeFor(string $url): ?Route
    {
        try {
            $route = $this->router->getRoutes()->match(Request::create(url($url)));
        } catch (HttpException) {
            return null;
        }

        return $route->isFallback ? null : $route;
    }

    private function finding(string $url, int $pageId, Route $route): Finding
    {
        $title = Value::string($this->pages->find($pageId)?->title);
        $name = $route->getName() !== null ? " ({$route->getName()})" : '';

        return new Finding(
            Severity::Warning,
            "The page \"{$title}\" at {$url} is hidden: the route /{$route->uri()}{$name} answers that URL, so visitors never see the page.",
            'Give the page another URL name, or move the route. When it is the admin\'s, choose another admin path with lynx:admin-path.',
        );
    }
}
