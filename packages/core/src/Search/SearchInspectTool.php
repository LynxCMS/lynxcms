<?php

declare(strict_types=1);

namespace LynxCms\Core\Search;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Agents\AgentReadTool;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * What the search index holds (ADR-0120), for agents: rows per language, the fields each
 * template indexes, and optionally a page's rows.
 *
 * @internal
 */
final class SearchInspectTool extends AgentReadTool
{
    public function __construct(private readonly SearchIndexReport $report) {}

    public static function name(): string
    {
        return 'search_inspect';
    }

    public static function description(): string
    {
        return 'Read what the search index holds: per language, how many pages have a row and whether plurals are reduced; per template, the fields it indexes (the title and searchable fields that give text). With "page", also the words indexed for that page in each language. Use it to explain why search finds or misses a page; search.rebuild rebuilds the index.';
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['page' => $schema->string()->description('A page id or path whose indexed words to show, e.g. "42" or "/courses/risk/".')];
    }

    public static function permission(): string
    {
        return 'search.manage';
    }

    public function handle(array $arguments): mixed
    {
        $report = ['languages' => $this->report->languages(), 'templates' => $this->report->templates()];
        $reference = Value::string($arguments['page'] ?? '');

        if ($reference === '') {
            return $report;
        }

        $page = lynx()->pages->get(ctype_digit($reference) ? (int) $reference : $reference);

        return $report + ($page instanceof Page && $page->id !== null ? ['page' => $this->report->page($page->id)] : ['error' => 'There is no such page.']);
    }
}
