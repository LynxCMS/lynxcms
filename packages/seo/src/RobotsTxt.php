<?php

declare(strict_types=1);

namespace LynxCms\Seo;

use Closure;

/**
 * The /robots.txt body. Editors add lines in the SEO settings; modules add theirs in a service
 * provider, e.g. app(RobotsTxt::class)->line(fn (): string => 'Sitemap: '.url('/sitemap.xml')).
 *
 * @api
 */
final class RobotsTxt
{
    /** @var list<string|Closure(): string> */
    private array $lines = [];

    /** @param  string|Closure(): string  $line  a closure is read on each request */
    public function line(string|Closure $line): void
    {
        $this->lines[] = $line;
    }

    public function render(): string
    {
        if ((bool) config('lynx-seo.block_non_production', true) && ! app()->isProduction()) {
            return "User-agent: *\nDisallow: /\n";
        }

        $rules = ['User-agent: *', 'Disallow:', ...$this->editorLines()];
        $added = array_values(array_filter(array_map(fn (string|Closure $line): string => trim(is_string($line) ? $line : $line()), $this->lines)));

        return implode("\n", $added === [] ? $rules : [...$rules, '', ...$added])."\n";
    }

    /** @return list<string> */
    private function editorLines(): array
    {
        $settings = lynx()->settings(SeoSchema::SETTINGS_TEMPLATE);
        $raw = $settings?->templateField(SeoSchema::ROBOTS_EXTRA) === null ? '' : $settings->getUnformatted(SeoSchema::ROBOTS_EXTRA);
        $lines = preg_split('/\R/', is_string($raw) ? $raw : '') ?: [];

        return array_values(array_filter(array_map(trim(...), $lines), fn (string $line): bool => $line !== ''));
    }
}
