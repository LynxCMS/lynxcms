<?php

declare(strict_types=1);

namespace LynxCms\Sitemap;

use LynxCms\Core\Fields\Choices\Choice;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * The sitemap's settings in effect: what editors set on the settings page, else the config.
 *
 * @api
 */
final class SitemapSettings
{
    /** URLs per file, within the protocol's maximum. */
    public function perFile(): int
    {
        $own = Value::int($this->value(SitemapSchema::PER_FILE));
        $perFile = $own > 0 ? $own : Value::int(config('lynx-sitemap.per_file'), 10000);

        return max(1, min(SitemapSchema::MAX_PER_FILE, $perFile));
    }

    /** SitemapSchema::GROUP_NONE or SitemapSchema::GROUP_TEMPLATE. */
    public function groupBy(): string
    {
        $own = $this->value(SitemapSchema::GROUP_BY);
        $groupBy = $own instanceof Choice ? $own->value : Value::string(config('lynx-sitemap.group_by'));

        return $groupBy === SitemapSchema::GROUP_TEMPLATE ? SitemapSchema::GROUP_TEMPLATE : SitemapSchema::GROUP_NONE;
    }

    /**
     * Templates left out: the config's and the editors'.
     *
     * @return list<string>
     */
    public function excluded(): array
    {
        $own = array_map(trim(...), explode(',', Value::string($this->value(SitemapSchema::EXCLUDE))));

        return array_values(array_unique(array_filter([...Value::strings(config('lynx-sitemap.exclude_templates', [])), ...$own])));
    }

    public function includesHidden(): bool
    {
        return (bool) config('lynx-sitemap.include_hidden', false);
    }

    /** A fingerprint of the settings, so a changed setting never serves an old sitemap. */
    public function fingerprint(): string
    {
        return md5(serialize([$this->perFile(), $this->groupBy(), $this->excluded(), $this->includesHidden(), url('/')]));
    }

    private function value(string $field): mixed
    {
        $settings = lynx()->settings(SitemapSchema::SETTINGS_TEMPLATE);

        return $settings instanceof Page && $settings->templateField($field) !== null ? $settings->get($field) : null;
    }
}
