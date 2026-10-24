<?php

declare(strict_types=1);

namespace LynxCms\Ai\Tools;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use LynxCms\Ai\AgentSession;
use LynxCms\Core\Agents\AgentToolkit;
use LynxCms\Core\Agents\PageHistory;
use LynxCms\Core\Agents\ReadToolRegistry;
use LynxCms\Core\Query\SelectorException;
use LynxCms\Core\Support\Value;

/**
 * The read tools shared with the MCP server: describe_schema, describe_field_types, find_pages, get_page,
 * get_page_history, and those modules register (ReadToolRegistry).
 */
final readonly class ReadTool implements Tool
{
    /**
     * @param  Closure(JsonSchema): array<string, Type>  $schema
     * @param  Closure(array<string, mixed>): mixed  $handler
     */
    private function __construct(
        private string $name,
        private string $description,
        private Closure $schema,
        private Closure $handler,
        private ?AgentSession $session = null,
    ) {}

    /**
     * @return list<self>
     */
    public static function all(AgentToolkit $toolkit, ?AgentSession $session = null): array
    {
        $tools = [
            new self('describe_schema', 'Describe the site: languages with fallback chains, templates and fields, field types, components, selector syntax. Call this first.',
                fn (JsonSchema $s): array => [], fn (array $a): mixed => $toolkit->describeSchema()),
            new self('describe_field_types', 'Field types with their description and accepted settings (max_length, min/max, format, templates...).',
                fn (JsonSchema $s): array => [], fn (array $a): mixed => lynx()->fields->types()),
            new self('find_pages', 'Find pages with a selector such as "template=course, title%=risk, sort=-title".',
                fn (JsonSchema $s): array => [
                    'selector' => $s->string()->required(),
                    'language' => $s->string(),
                    'limit' => $s->integer()->description('Maximum pages to return; capped by the site. Use start= in the selector to page.'),
                ], fn (array $a): mixed => $toolkit->findPages(Value::string($a['selector'] ?? ''), Value::nullableString($a['language'] ?? null), Value::nullableInt($a['limit'] ?? null))),
            new self('get_page', 'Read one page by id, default-language path or page name, with every stored value per language.',
                fn (JsonSchema $s): array => [
                    'page' => $s->string()->required(),
                    'language' => $s->string(),
                ], fn (array $a): mixed => $toolkit->getPage(Value::string($a['page'] ?? ''), Value::nullableString($a['language'] ?? null)) ?? ['error' => 'Page not found.']),
            new self('get_page_history', 'A page\'s old URLs (and whether each still redirects), the pages that reference it (menu items included), its latest activity and revisions (ids for pages_restore_revision), and whether it has a draft. Check it before renaming, moving or deleting a page.',
                fn (JsonSchema $s): array => [
                    'page' => $s->string()->required(),
                    'activity_limit' => $s->integer(),
                ], fn (array $a): mixed => app(PageHistory::class)->for(Value::string($a['page'] ?? ''), max(1, Value::int($a['activity_limit'] ?? null, 20))) ?? ['error' => 'Page not found.']),
            ...array_map(
                fn (string $tool): self => new self($tool::name(), $tool::description(), $tool::schema(...), fn (array $a): mixed => app(ReadToolRegistry::class)->run($tool, $a)),
                app(ReadToolRegistry::class)->all(),
            ),
        ];

        return array_map(fn (self $tool): self => new self($tool->name, $tool->description, $tool->schema, $tool->handler, $session), $tools);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function schema(JsonSchema $schema): array
    {
        return ($this->schema)($schema);
    }

    public function handle(Request $request): string
    {
        $this->session?->assertTimeLeft();

        try {
            $result = ($this->handler)($request->all());
        } catch (SelectorException|AuthorizationException $e) {
            $result = ['error' => $e->getMessage()];
        }

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
