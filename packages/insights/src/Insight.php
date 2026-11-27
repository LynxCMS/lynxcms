<?php

declare(strict_types=1);

namespace LynxCms\Insights;

/**
 * One suggested change and why (ADR-0155). A source returns these; the report stores them.
 *
 * - pages: the pages the insight is about. The report records when each was last changed, and
 *   approving refuses once one changed since.
 * - plan: the action calls that make the change, by action name or class, run in order and as a
 *   whole when an editor approves. Empty for an insight that only informs.
 * - evidence: the facts it rests on, as label and value, shown with it.
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

    /**
     * @param  list<int>  $pages
     * @param  list<array{action: string, arguments: array<string, mixed>}>  $plan
     * @param  list<array{label: string, value: string|int|float}>  $evidence
     */
    public function __construct(
        public string $title,
        public string $reason,
        public array $pages = [],
        public array $plan = [],
        public array $evidence = [],
        public float $weight = 0.0,
        public string $kind = self::CONTENT,
        public string $subject = '',
    ) {}

    /** The same for the same suggestion in every report, so a decision on it is remembered. */
    public function fingerprint(string $source): string
    {
        $pages = $this->pages;
        sort($pages);
        $subject = match (true) {
            $this->subject !== '' => 'subject:'.$this->subject,
            $pages !== [] => 'pages:'.implode(',', $pages),
            default => 'title:'.$this->title,
        };

        return sha1(implode('|', [$source, $this->kind, $subject]));
    }
}
