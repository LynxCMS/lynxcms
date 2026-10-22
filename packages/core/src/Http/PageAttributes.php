<?php

declare(strict_types=1);

namespace LynxCms\Core\Http;

/**
 * Request attributes the front-end route sets, for middleware that runs around it, such as a
 * page cache (ADR-0064).
 *
 * @api
 */
final class PageAttributes
{
    /** true on the catch-all page route. */
    public const string PAGE_ROUTE = 'lynx.page_route';

    /** The code of the language the URL selected. */
    public const string LANGUAGE = 'lynx.language';

    /** The live page the response shows, set before its view renders; not set for redirects, gates or draft previews. */
    public const string PAGE = 'lynx.page';
}
