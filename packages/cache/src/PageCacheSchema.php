<?php

declare(strict_types=1);

namespace LynxCms\Cache;

/**
 * Names of the fields and the template the page cache installs (ADR-0064). Its settings live on
 * a hidden system page (ADR-0008); what only the server can decide (on or off, driver, hosts)
 * stays in config.
 *
 * @api
 */
final class PageCacheSchema
{
    public const string SETTINGS_TEMPLATE = 'page-cache-settings';

    public const string SETTINGS_NAME = 'page-cache-settings';

    /** Seconds a page is kept when its rule sets none; empty uses lynx-cache.pages.lifetime. */
    public const string LIFETIME = 'page_cache_lifetime';

    /** Template rules as JSON, name => rule or false; written by page_cache.save_rules. */
    public const string RULES = 'page_cache_rules';

    public const array SCOPES = ['self', 'parents', 'children', 'siblings', 'home', 'all'];

    /**
     * Fields to create: name => [type, label, settings, help].
     *
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>, 3: string}>
     */
    public static function fields(): array
    {
        return [
            self::LIFETIME => ['integer', 'Default lifetime', ['min' => 0], 'Seconds a page is kept when its template sets none. 0 keeps it until content changes. Empty: the config value.'],
            self::RULES => ['textarea', 'Template rules', [], 'Written by the page cache screen and the page_cache.save_rules action.'],
        ];
    }
}
