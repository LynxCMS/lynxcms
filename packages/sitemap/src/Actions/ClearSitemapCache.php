<?php

declare(strict_types=1);

namespace LynxCms\Sitemap\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Sitemap\SitemapCache;

/**
 * Drops the built sitemap, so the next request builds it again. Content changes do this on their
 * own; this is for changes Lynx cannot see, such as a new config value.
 */
final class ClearSitemapCache extends Action
{
    public function __construct(private readonly SitemapCache $cache) {}

    public static function name(): string
    {
        return 'sitemap.clear_cache';
    }

    public static function description(): string
    {
        return 'Drop the cached sitemap so the next request builds it again. Page changes already do this; use it after changing the sitemap config.';
    }

    public static function permission(): string
    {
        return 'sitemap.manage';
    }

    public static function rules(): array
    {
        return [];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Input $input, ActionContext $context): bool
    {
        $context->changes->updated('sitemap', null, 'Sitemap cache cleared');

        if (! $context->dryRun) {
            $this->cache->clear();
        }

        return true;
    }
}
