<?php

declare(strict_types=1);

namespace LynxCms\Insights\Sources;

use LynxCms\Core\Pages\References\ReferenceReports;
use LynxCms\Core\Query\PageFinder;
use LynxCms\Core\Support\Value;
use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightPeriod;
use LynxCms\Insights\InsightSource;
use LynxCms\Insights\InsightText;

/**
 * Published pages that link to a page visitors cannot open: unpublished, in the trash, or gone
 * (the page references report, ADR-0145). One insight about the linking pages, most linked first.
 * It only informs: whether to publish the target or remove the link is the editor's call.
 *
 * @internal
 */
final readonly class BrokenLinks implements InsightSource
{
    /** The pages one insight names. */
    public const int PAGES = 20;

    /** References read per query. */
    private const int BATCH = 500;

    private const string KEY = 'lynx-insights::sources.broken_links';

    public function __construct(private ReferenceReports $reports, private PageFinder $finder, private InboundLinks $links) {}

    public function key(): string
    {
        return 'broken-links';
    }

    public function label(): string
    {
        return Value::string(__(self::KEY.'.label'));
    }

    public function insights(InsightPeriod $period): array
    {
        $references = $this->fromPublishedPages();

        if ($references === []) {
            return [];
        }

        $linking = array_values(array_unique(array_column($references, 'page')));
        $counts = $this->links->counts($linking);
        $pages = array_slice($this->links->sort($linking, $counts), 0, self::PAGES);
        $targets = count(array_unique(array_column($references, 'target')));
        $replace = ['count' => count($linking), 'links' => count($references), 'targets' => $targets];

        return [new Insight(
            title: new InsightText(self::KEY.'.title', $replace, count($linking)),
            reason: new InsightText(self::KEY.'.reason', $replace),
            pages: $pages,
            evidence: [
                ['label' => new InsightText(self::KEY.'.evidence.links'), 'value' => count($references)],
                ['label' => new InsightText(self::KEY.'.evidence.targets'), 'value' => $targets],
            ],
            weight: (float) count($references),
            subject: 'broken-links',
        )];
    }

    /**
     * The broken references whose page visitors can open.
     *
     * @return list<array{page: int, target: int}>
     */
    private function fromPublishedPages(): array
    {
        $published = array_flip($this->finder->ids('include=hidden'));
        $found = [];

        for ($start = 0; ; $start += self::BATCH) {
            $batch = $this->reports->broken(null, self::BATCH, $start);

            foreach ($batch['rows'] as $row) {
                if (isset($published[$row['reference']->pageId])) {
                    $found[] = ['page' => $row['reference']->pageId, 'target' => $row['reference']->linkedPageId];
                }
            }

            if ($start + self::BATCH >= $batch['total']) {
                return $found;
            }
        }
    }
}
