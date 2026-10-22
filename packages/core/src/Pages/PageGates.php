<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks a page passes before the front end renders it (ADR-0062). Modules add gates in a
 * service provider: app(PageGates::class)->add(fn (Page $page, Request $request): ?Response => ...).
 * The first gate that returns a response is answered instead of the page; null lets it through.
 *
 * @api
 */
final class PageGates
{
    /** @var list<Closure(Page, Request): ?Response> */
    private array $gates = [];

    /** @param  Closure(Page, Request): ?Response  $gate */
    public function add(Closure $gate): void
    {
        $this->gates[] = $gate;
    }

    /** The response of the first gate that stops the page, or null. */
    public function respond(Page $page, Request $request): ?Response
    {
        foreach ($this->gates as $gate) {
            $response = $gate($page, $request);

            if ($response instanceof Response) {
                return $response;
            }
        }

        return null;
    }
}
