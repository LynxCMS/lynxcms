<?php

declare(strict_types=1);

namespace LynxCms\Sitemap;

/**
 * Names of the fields and the template the sitemap module installs (ADR-0057). Its settings live
 * on a hidden system page (ADR-0008).
 *
 * @api
 */
final class SitemapSchema
{
    public const string SETTINGS_TEMPLATE = 'sitemap-settings';

    public const string SETTINGS_NAME = 'sitemap-settings';

    /** URLs per file; empty uses lynx-sitemap.per_file. */
    public const string PER_FILE = 'sitemap_per_file';

    /** "none" or "template"; empty uses lynx-sitemap.group_by. */
    public const string GROUP_BY = 'sitemap_group_by';

    /** Template names left out, comma separated, on top of lynx-sitemap.exclude_templates. */
    public const string EXCLUDE = 'sitemap_exclude_templates';

    public const string GROUP_NONE = 'none';

    public const string GROUP_TEMPLATE = 'template';

    /** The protocol's maximum of URLs per file. */
    public const int MAX_PER_FILE = 50000;

    /**
     * Fields to create: name => [type, label, settings, help].
     *
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>, 3: string}>
     */
    public static function fields(): array
    {
        return [
            self::PER_FILE => ['integer', 'URLs per file', ['min' => 1, 'max' => self::MAX_PER_FILE], 'Past this many URLs the sitemap splits into parts under an index. Empty: the config value.'],
            self::GROUP_BY => ['choice', 'Group by', ['options' => [['value' => self::GROUP_NONE, 'label' => 'One list'], ['value' => self::GROUP_TEMPLATE, 'label' => 'One sitemap per template']]], 'One sitemap per template helps big sites see indexing per kind of page.'],
            self::EXCLUDE => ['text', 'Templates left out', [], 'Template names, comma separated, whose pages never appear.'],
        ];
    }
}
