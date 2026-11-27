<?php

declare(strict_types=1);

namespace LynxCms\Insights;

/**
 * One suggested change and why (ADR-0155). A source returns these; the report stores them.
 *
 * - title and reason: an InsightText, a translation key with its replacements, so each editor
 *   reads them in their own language. A plain string shows as it is.
 * - pages: the pages the insight is about. The report records when each was last changed, and
 *   approving refuses once one changed since.
 * - plan: the action calls that make the change, by action name or class, run in order and as a
 *   whole when an editor approves. Empty for an insight that only informs.
 * - evidence: the facts it rests on, as label (an InsightText or a string) and value.
 * - weight: orders a report, heaviest first. A dismissed insight comes back early once its
 *   weight doubled.
 * - subject: what makes two insights of a source the same one across reports, such as a language
 *   code; by default the pages it is about, else its title.
 *
 * @api
 */
final readonly class Insight
{
    public const string CONTENT = 'content';

    public InsightText $title;

    public InsightText $reason;

    /** @var list<array{label: InsightText, value: string|int|float}> */
    public array $evidence;

    /**
     * @param  list<int>  $pages
     * @param  list<array{action: string, arguments: array<string, mixed>}>  $plan
     * @param  list<array{label: InsightText|string, value: string|int|float}>  $evidence
     */
    public function __construct(
        InsightText|string $title,
        InsightText|string $reason,
        public array $pages = [],
        public array $plan = [],
        array $evidence = [],
        public float $weight = 0.0,
        public string $kind = self::CONTENT,
        public string $subject = '',
    ) {
        $this->title = InsightText::from($title);
        $this->reason = InsightText::from($reason);
        $this->evidence = array_map(fn (array $fact): array => ['label' => InsightText::from($fact['label']), 'value' => $fact['value']], $evidence);
    }

    /** The same for the same suggestion in every report, so a decision on it is remembered. */
    public function fingerprint(string $source): string
    {
        $pages = $this->pages;
        sort($pages);
        $subject = match (true) {
            $this->subject !== '' => 'subject:'.$this->subject,
            $pages !== [] => 'pages:'.implode(',', $pages),
            default => 'title:'.$this->title->key.json_encode($this->title->replace, JSON_THROW_ON_ERROR),
        };

        return sha1(implode('|', [$source, $this->kind, $subject]));
    }
}
