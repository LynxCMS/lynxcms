<?php

declare(strict_types=1);

namespace LynxCms\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Pages\PageUrl;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional. When the default language has no URL prefix, /{default-code}/about answers with a
 * 301 to /about. GET requests only.
 */
final readonly class RedirectDefaultLocale
{
    public function __construct(private LanguageRegistry $languages) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || ! $this->languages->hasDefault()) {
            return $next($request);
        }

        $default = $this->languages->default();
        $segments = explode('/', trim($request->path(), '/'));

        if ($default->url_prefix === '' && ($segments[0] ?? '') === $default->code && $this->languages->byPrefix($default->code) === null) {
            array_shift($segments);
            $target = PageUrl::of('', implode('/', $segments));
            $query = $request->getQueryString();

            return redirect()->away(rtrim(url('/'), '/').$target.($query ? '?'.$query : ''), 301);
        }

        return $next($request);
    }
}
