<?php

declare(strict_types=1);

namespace LynxCms\Core\Events;

/**
 * Site texts (strings with scope "site") changed: every page may show them.
 *
 * @api
 */
final readonly class SiteStringsSaved implements ContentChanged
{
    /**
     * @param  list<string>  $keys
     */
    public function __construct(public array $keys, public string $language) {}

    public function affectedPages(): null
    {
        return null;
    }
}
