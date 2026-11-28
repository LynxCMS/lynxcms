<?php

declare(strict_types=1);

namespace LynxCms\Insights\Sources;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightPeriod;
use LynxCms\Insights\InsightSource;
use LynxCms\Insights\InsightText;

/**
 * Drafts nobody saved during the report's period (ADR-0039 drafts): one insight about their live
 * pages, most linked first. It only informs: discarding a draft loses work, and publishing one is
 * the editor's decision, in the editor.
 *
 * @internal
 */
final readonly class StaleDrafts implements InsightSource
{
    /** The pages one insight names, most linked first. */
    public const int PAGES = 20;

    private const string KEY = 'lynx-insights::sources.stale_drafts';

    public function __construct(private InboundLinks $links) {}

    public function key(): string
    {
        return 'stale-drafts';
    }

    public function label(): string
    {
        return Value::string(__(self::KEY.'.label'));
    }

    public function insights(InsightPeriod $period): array
    {
        $drafts = DB::table(Tables::PAGES)->whereNotNull('draft_of')->where('updated_at', '<', $period->start)->pluck('updated_at', 'draft_of')->all();

        if ($drafts === []) {
            return [];
        }

        $live = Value::ints(array_keys($drafts));
        $links = $this->links->counts($live);
        $pages = array_slice($this->links->sort($live, $links), 0, self::PAGES);
        $oldest = Carbon::parse(Value::string(min($drafts)));
        $replace = ['count' => count($live)];

        return [new Insight(
            title: new InsightText(self::KEY.'.title', $replace, count($live)),
            reason: new InsightText(self::KEY.'.reason', $replace + ['days' => $period->days()]),
            pages: $pages,
            evidence: [
                ['label' => new InsightText(self::KEY.'.evidence.drafts'), 'value' => count($live)],
                ['label' => new InsightText(self::KEY.'.evidence.oldest'), 'value' => (int) $oldest->diffInDays(Carbon::now())],
            ],
            weight: (float) (count($live) + array_sum(array_map(fn (int $page): int => $links[$page] ?? 0, $pages))),
            subject: 'drafts',
        )];
    }
}
