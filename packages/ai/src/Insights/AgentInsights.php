<?php

declare(strict_types=1);

namespace LynxCms\Ai\Insights;

use Laravel\Ai\Responses\StructuredAgentResponse;
use LynxCms\Ai\AgentSession;
use LynxCms\Ai\LynxAi;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Contracts\Metrics\PageMetrics;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Agents\AgentToolkit;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Query\PageFinder;
use LynxCms\Core\Support\Value;
use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightPeriod;
use LynxCms\Insights\InsightSource;
use LynxCms\Insights\Sources\InboundLinks;
use LynxCms\Insights\Sources\Metrics;
use Throwable;

/**
 * The Insights Agent as an insight source: off unless lynx-insights.ai.enabled, since it sends page
 * text to the AI provider and uses its credits. One run per report: the agent reads the site,
 * starting from its most viewed (or most linked) pages, and returns at most lynx-insights.ai.max
 * suggestions. Each change is then planned by the built-in agent in plan mode (ADR-0020), which
 * rolls back, and its steps become the insight's plan. A plan that fails, deletes, or refers to a
 * page created while planning keeps the insight as information only. Both agents run as the
 * system: nothing changes until an editor approves, with the editor's own permissions.
 *
 * @internal
 */
final readonly class AgentInsights implements InsightSource
{
    /** The pages one insight names. */
    private const int PAGES = 20;

    /** Argument names that hold a page id in the built-in actions. */
    private const array PAGE_ARGUMENTS = ['id', 'page', 'parent', 'page_id'];

    public function __construct(
        private AgentToolkit $toolkit,
        private LynxAi $ai,
        private ActionRunner $runner,
        private LanguageRegistry $languages,
        private PageFinder $finder,
        private InboundLinks $links,
        private Metrics $metrics,
    ) {}

    public function key(): string
    {
        return 'insights-agent';
    }

    public function label(): string
    {
        return Value::string(__('lynx-ai::insights.label'));
    }

    public function insights(InsightPeriod $period): array
    {
        if (! filter_var(config('lynx-insights.ai.enabled'), FILTER_VALIDATE_BOOL)) {
            return [];
        }

        $max = max(1, Value::int(config('lynx-insights.ai.max'), 5));
        $found = array_slice($this->ask($period, $max), 0, $max);
        $insights = [];

        foreach ($found as $index => $suggestion) {
            $insights[] = $this->insight(Value::map($suggestion), count($found) - $index);
        }

        return $insights;
    }

    /**
     * @return list<mixed> what the agent suggested, most important first
     */
    private function ask(InsightPeriod $period, int $max): array
    {
        $session = new AgentSession(Actor::system());
        $agent = new InsightsAgent($session, $this->toolkit, $max, $this->languages->default()->name);
        $response = $this->runner->actingAs($session->actor, fn (): mixed => $agent->prompt(
            $this->prompt($period),
            provider: $this->provider(),
            model: Value::nullableString(config('lynx-insights.ai.model') ?? config('lynx.ai.model')),
        ));

        return $response instanceof StructuredAgentResponse ? array_values(Value::array($response->toArray()['insights'] ?? [])) : [];
    }

    /** @return array<mixed>|string|null the analysis's provider: its own, else the built-in agent's */
    private function provider(): array|string|null
    {
        $own = config('lynx-insights.ai.provider');

        return is_array($own) || is_string($own) ? $own : LynxAi::provider();
    }

    private function prompt(InsightPeriod $period): string
    {
        $limit = max(1, Value::int(config('lynx-insights.ai.pages'), 50));
        $metrics = $this->metrics->available();
        $ranked = $metrics instanceof PageMetrics
            ? array_keys($metrics->top(PageMetrics::VIEW, $period->days(), $limit))
            : array_slice($this->links->sort($ids = $this->finder->ids('include=hidden'), $this->links->counts($ids)), 0, $limit);
        $pages = array_map(fn (int $id): string => "#{$id} ".lynx()->pages->get($id)?->path(), $ranked);

        return "The report covers {$period->start->toDateString()} to {$period->end->toDateString()}. "
            .'Start from these pages, '.($metrics instanceof PageMetrics ? 'the most viewed' : 'the most linked').' first:'
            ."\n".implode("\n", $pages);
    }

    /**
     * @param  array<array-key, mixed>  $suggestion
     */
    private function insight(array $suggestion, int $weight): Insight
    {
        $change = trim(Value::string($suggestion['change'] ?? ''));

        return new Insight(
            title: Value::string($suggestion['title'] ?? ''),
            reason: Value::string($suggestion['reason'] ?? ''),
            pages: array_slice($this->existing(Value::ints($suggestion['pages'] ?? [])), 0, self::PAGES),
            plan: $change === '' ? [] : $this->plan($change),
            evidence: array_values(array_map(fn (mixed $fact): array => [
                'label' => Value::string(Value::map($fact)['label'] ?? ''),
                'value' => Value::string(Value::map($fact)['value'] ?? ''),
            ], Value::array($suggestion['evidence'] ?? []))),
            weight: (float) $weight,
        );
    }

    /**
     * The built-in agent's plan for the change, as action calls; none when planning fails, the plan
     * deletes, or it refers to a page that exists only while planning.
     *
     * @return list<array{action: string, arguments: array<string, mixed>}>
     */
    private function plan(string $change): array
    {
        try {
            $run = $this->ai->ask($change, actor: Actor::system());
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $plan = array_map(fn (array $step): array => ['action' => $step['action'], 'arguments' => $step['arguments']], $run->steps);
        $named = array_merge([], ...array_map(fn (array $step): array => $this->pageIds($step['arguments']), $plan));

        return $run->isDestructive() || array_diff($named, $this->existing($named)) !== [] ? [] : $plan;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return list<int>
     */
    private function pageIds(array $arguments): array
    {
        return array_values(array_filter(array_map(
            fn (string $key): ?int => is_int($arguments[$key] ?? null) ? $arguments[$key] : null,
            self::PAGE_ARGUMENTS,
        ), fn (?int $id): bool => $id !== null));
    }

    /**
     * @param  list<int>  $ids
     * @return list<int> the ids, in their order, of pages that exist
     */
    private function existing(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $found = array_flip($this->finder->ids('id='.implode('|', array_unique($ids)).', include=all'));

        return array_values(array_unique(array_filter($ids, fn (int $id): bool => isset($found[$id]))));
    }
}
