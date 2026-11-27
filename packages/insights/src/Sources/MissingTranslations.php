<?php

declare(strict_types=1);

namespace LynxCms\Insights\Sources;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Query\PageFinder;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Translation\TranslationProviders;
use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightPeriod;
use LynxCms\Insights\InsightSource;

/**
 * Published pages with no version in an active language: one insight per language, about the
 * pages most linked to from other pages (the page references index, ADR-0145). With a translation
 * provider, its plan translates them into their drafts, for an editor to review and publish.
 *
 * @internal
 */
final readonly class MissingTranslations implements InsightSource
{
    /** The pages one insight names and translates, most linked first. */
    public const int PAGES = 20;

    /** Ids per query, below every driver's limit on bound values. */
    private const int CHUNK = 500;

    public function __construct(
        private LanguageRegistry $languages,
        private PageFinder $finder,
        private TranslationProviders $providers,
    ) {}

    public function key(): string
    {
        return 'missing-translations';
    }

    public function label(): string
    {
        return Value::string(__('lynx-insights::sources.missing_translations.label'));
    }

    public function insights(InsightPeriod $period): array
    {
        $default = $this->languages->default();
        $published = $this->finder->ids('include=hidden');
        $insights = [];

        foreach ($this->languages->active() as $language) {
            $missing = $language->id === $default->id ? [] : $this->untranslated($published, $language);

            if ($missing !== []) {
                $insights[] = $this->insight($language, $missing);
            }
        }

        return $insights;
    }

    /**
     * @param  list<int>  $missing
     */
    private function insight(Language $language, array $missing): Insight
    {
        $links = $this->inboundLinks($missing);
        usort($missing, fn (int $a, int $b): int => [$links[$b] ?? 0, $a] <=> [$links[$a] ?? 0, $b]);
        $pages = array_slice($missing, 0, self::PAGES);
        $linked = array_sum(array_map(fn (int $page): int => $links[$page] ?? 0, $pages));
        $replace = ['count' => count($missing), 'language' => $language->name];

        return new Insight(
            title: trans_choice('lynx-insights::sources.missing_translations.title', count($missing), $replace),
            reason: Value::string(__('lynx-insights::sources.missing_translations.reason', $replace + ['shown' => count($pages)])),
            pages: $pages,
            plan: $this->plan($language, $pages),
            evidence: [
                ['label' => Value::string(__('lynx-insights::sources.missing_translations.evidence.pages', $replace)), 'value' => count($missing)],
                ['label' => Value::string(__('lynx-insights::sources.missing_translations.evidence.links')), 'value' => $linked],
            ],
            weight: (float) (count($missing) + $linked),
            subject: $language->code,
        );
    }

    /**
     * @param  list<int>  $pages
     * @return list<array{action: string, arguments: array<string, mixed>}>
     */
    private function plan(Language $language, array $pages): array
    {
        if (! $this->providers->hasDefault()) {
            return [];
        }

        return array_map(fn (int $page): array => ['action' => 'pages.translate', 'arguments' => ['page' => $page, 'to' => $language->code, 'draft' => true]], $pages);
    }

    /**
     * The pages with no name in the language: a page gets one when it is translated or named there.
     *
     * @param  list<int>  $pages
     * @return list<int>
     */
    private function untranslated(array $pages, Language $language): array
    {
        $named = [];

        foreach (array_chunk($pages, self::CHUNK) as $chunk) {
            $named = [...$named, ...Value::ints(DB::table(Tables::PAGE_LANGUAGES)->where('language_id', $language->id)->whereIn('page_id', $chunk)->pluck('page_id')->all())];
        }

        return array_values(array_diff($pages, $named));
    }

    /**
     * How many other pages link to each page.
     *
     * @param  list<int>  $pages
     * @return array<int, int>
     */
    private function inboundLinks(array $pages): array
    {
        $links = [];

        foreach (array_chunk($pages, self::CHUNK) as $chunk) {
            $rows = DB::table(Tables::PAGE_REFERENCES)->whereIn('linked_page_id', $chunk)->whereColumn('page_id', '!=', 'linked_page_id')
                ->groupBy('linked_page_id')->selectRaw('linked_page_id, count(distinct page_id) as total')->pluck('total', 'linked_page_id')->all();

            foreach ($rows as $page => $total) {
                $links[(int) $page] = Value::int($total);
            }
        }

        return $links;
    }
}
