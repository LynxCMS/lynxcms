<?php

declare(strict_types=1);

namespace LynxCms\Seo\Insights;

use LynxCms\Core\Query\PageFinder;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;
use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightPeriod;
use LynxCms\Insights\InsightSource;
use LynxCms\Insights\InsightText;
use LynxCms\Insights\Sources\InboundLinks;
use LynxCms\Seo\SeoSchema;

/**
 * Published pages with SEO fields whose description falls back to the site's default: the page has
 * no description, and none of the fields lynx-seo.description_fields takes it from has a value in
 * the default language. Pages marked noindex, and hidden pages, are left out. One insight about the
 * most linked of them; it only informs. Registered only when lynxcms/insights is installed.
 *
 * @internal
 */
final readonly class MissingDescriptions implements InsightSource
{
    /** The pages one insight names. */
    public const int PAGES = 20;

    private const string KEY = 'lynx-seo::insights.missing_descriptions';

    public function __construct(private PageFinder $finder, private TemplateRegistry $templates, private InboundLinks $links) {}

    public function key(): string
    {
        return 'missing-descriptions';
    }

    public function label(): string
    {
        return Value::string(__(self::KEY.'.label'));
    }

    public function insights(InsightPeriod $period): array
    {
        $templates = $this->templates->all()->filter(fn ($template): bool => $template->hasUrl() && $this->templates->fields($template)->has(SeoSchema::DESCRIPTION));

        if ($templates->isEmpty()) {
            return [];
        }

        $empty = array_map(fn (string $field): string => "{$field}=", [SeoSchema::DESCRIPTION, ...$this->fallbackFields()]);
        $missing = $this->finder->ids(implode(', ', ['template='.$templates->pluck('name')->implode('|'), ...$empty, SeoSchema::NOINDEX.'!=1']));

        if ($missing === []) {
            return [];
        }

        $counts = $this->links->counts($missing);
        $pages = array_slice($this->links->sort($missing, $counts), 0, self::PAGES);
        $replace = ['count' => count($missing)];

        return [new Insight(
            title: new InsightText(self::KEY.'.title', $replace, count($missing)),
            reason: new InsightText(self::KEY.'.reason', $replace),
            pages: $pages,
            evidence: [
                ['label' => new InsightText(self::KEY.'.evidence.pages'), 'value' => count($missing)],
                ['label' => new InsightText(self::KEY.'.evidence.links'), 'value' => array_sum(array_map(fn (int $page): int => $counts[$page] ?? 0, $pages))],
            ],
            weight: (float) count($missing),
            subject: 'missing-descriptions',
        )];
    }

    /**
     * The fields a description falls back to that exist on the site: a selector on an unknown
     * field fails.
     *
     * @return list<string>
     */
    private function fallbackFields(): array
    {
        return array_values(array_filter(Value::strings(config('lynx-seo.description_fields', [])), fn (string $name): bool => lynx()->fields->find($name) !== null));
    }
}
