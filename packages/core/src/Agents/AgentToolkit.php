<?php

declare(strict_types=1);

namespace LynxCms\Core\Agents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\Action;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Components\ComponentRegistry;
use LynxCms\Core\Components\RegisteredComponent;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Lynx;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Strings\PageStrings;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Everything an AI agent can do, shared by the MCP server and the built-in laravel/ai agent
 * (ADR-0015, ADR-0020): read tools, and every registered action as a write tool with dry runs,
 * the language allowlist and the activity log.
 *
 * @api
 */
final readonly class AgentToolkit
{
    public function __construct(
        private Lynx $lynx,
        private ActionRegistry $actions,
        private ActionRunner $runner,
        private LanguageRegistry $languages,
        private LanguageContext $context,
        private TemplateRegistry $templates,
        private ComponentRegistry $components,
        private Authorizer $authorizer,
    ) {}

    /**
     * Tool name for an action: "pages.save" becomes "pages_save".
     */
    public static function toolName(string $actionName): string
    {
        return str_replace('.', '_', $actionName);
    }

    /**
     * @return array<string, class-string<Action>>
     */
    public function writeTools(): array
    {
        $tools = [];

        foreach ($this->actions->tools() as $name => $class) {
            $tools[self::toolName($name)] = $class;
        }

        return $tools;
    }

    /**
     * Input schema of an action's tool, with the dry_run flag added.
     *
     * @param  class-string<Action>  $action
     * @return array<string, Type>
     */
    public function schemaFor(string $action, ?JsonSchemaTypeFactory $schema = null): array
    {
        $schema ??= new JsonSchemaTypeFactory;

        return $action::schema($schema) + [
            'dry_run' => $schema->boolean()->description('Validate and return the changes without writing anything. Use this first and show the user the changes.'),
        ];
    }

    /**
     * @param  class-string<Action>  $action
     */
    public function descriptionFor(string $action): string
    {
        return $action::description().($action::destructive() ? ' DESTRUCTIVE: removes data; always dry-run first and ask the user to confirm.' : '');
    }

    /**
     * Run an action for an agent.
     *
     * @param  class-string<Action>  $action
     * @param  array<string, mixed>  $arguments
     * @return array{ok: false, errors: array<string, array<string>>}|array{ok: true, dry_run: bool, destructive: bool, changes: list<string>, result: mixed}
     */
    public function run(string $action, array $arguments, Actor $actor): array
    {
        $dryRun = (bool) ($arguments['dry_run'] ?? false);
        unset($arguments['dry_run']);

        try {
            $this->guardLanguages($arguments);
            $result = $this->runner->actingAs($actor, fn (): ActionResult => $this->runner->run($action, $arguments, $dryRun));
        } catch (ValidationException $e) {
            return ['ok' => false, 'errors' => array_map(Value::strings(...), Value::map($e->errors()))];
        }

        return [
            'ok' => true,
            'dry_run' => $result->dryRun,
            'destructive' => $result->changes->isDestructive(),
            'changes' => $result->changes->summary(),
            'result' => $this->serialize($result->value),
        ];
    }

    /**
     * The whole content model: languages, templates with their fields, field types, components.
     *
     * @return array<string, mixed>
     */
    public function describeSchema(): array
    {
        $default = $this->languages->default();

        return [
            'languages' => $this->languages->all()->map(fn (Language $l): array => $l->toSummary() + [
                'chain' => array_map(fn (Language $c): string => $c->code, $this->languages->chain($l)),
            ])->values()->all(),
            'default_language' => $default->code,
            'current_language' => $this->context->current()->code,
            'templates' => $this->templates->all()->map(fn (Template $t): array => [
                'name' => $t->name,
                'label' => $t->label,
                'has_url' => $t->hasUrl(),
                'singleton' => $t->isSingleton(),
                'allowed_templates' => $t->allowedTemplates(),
                'allow_children' => $t->allowsChildren(),
                'child_templates' => $t->childTemplates(),
                'parent_templates' => $t->parentTemplates(),
                'fields' => $this->templates->fields($t)->map(fn (TemplateField $f): array => $f->toSummary())->values()->all(),
            ])->values()->all(),
            'field_types' => $this->lynx->fields->types(),
            'formatters' => array_filter(array_map(fn (string $class): array => $this->lynx->formatters()->forType($class::name()), $this->lynx->fieldTypes()->all())),
            'components' => array_map(fn (RegisteredComponent $c): array => [
                'alias' => $c->alias,
                'description' => $c->description,
                'settings' => array_map(fn (Type $s): array => $s->toArray(), $c->settingsSchema(new JsonSchemaTypeFactory)),
            ], array_values($this->components->all())),
            'selector_help' => 'Selectors: "template=course, parent=/courses/, title%=word, price<300, sort=-title, limit=10". Operators: = != < > <= >= %= (contains) ^= (starts) $= (ends). Use | for OR values. Add "language=pt" to match on Portuguese values with fallback. find_pages returns at most '.Value::int(config('lynx.agents.max_limit', 100), 100).' pages per call; page with start=.',
        ];
    }

    /**
     * Find pages for an agent. Results are capped at config("lynx.agents.max_limit"), whatever the
     * selector or $limit asks for; "total" still counts every match so the agent can page with start=.
     *
     * @return array<string, mixed>
     */
    public function findPages(string $selector, ?string $language = null, ?int $limit = null): array
    {
        // pages.view held anywhere, then results limited to the templates and branches it covers (ADR-0033).
        $this->authorizer->authorize($this->runner->actor(), 'pages.view');
        $scope = $this->authorizer->scopeOf($this->runner->actor(), 'pages.view');
        $max = max(1, Value::int(config('lynx.agents.max_limit', 100), 100));
        $cap = $limit === null ? $max : min(max(1, $limit), $max);
        // An explicit argument is the number wanted (capped); otherwise the selector's limit= or the default.
        $default = $limit === null ? Value::int(config('lynx.agents.default_limit', 25), 25) : $cap;

        $run = function () use ($selector, $cap, $default, $scope): array {
            $pages = $this->lynx->pages->find($selector, maxLimit: $cap, defaultLimit: $default, scope: $scope);

            return [
                'total' => $pages->total(),
                'returned' => $pages->count(),
                'max_limit' => $cap,
                'pages' => $pages->map(fn (Page $p): array => [
                    'id' => $p->id,
                    'template' => $p->template()->name,
                    'title' => $p->templateField('title') instanceof TemplateField ? Value::string($p->get('title')) : $p->name(),
                    'url' => $p->url(),
                ]),
            ];
        };

        return $language === null ? $run() : $this->context->with($language, fn (): array => $run());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPage(int|string $reference, ?string $language = null): ?array
    {
        $run = function () use ($reference): ?array {
            $page = $this->lynx->pages->get($reference);

            if ($page === null) {
                return null;
            }

            $this->authorizer->authorize($this->runner->actor(), 'pages.view', $page);

            $strings = app(PageStrings::class)->describe($page);

            return $page->toArray() + ['values_by_language' => $page->valuesByLanguage()] + ($strings === [] ? [] : ['strings' => $strings]);
        };

        return $language === null ? $run() : $this->context->with($language, fn () => $run());
    }

    public static function usageGuide(): string
    {
        return (string) file_get_contents(__DIR__.'/../../resources/agents/usage-guide.md');
    }

    public function serialize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof Page => $value->toArray(),
            $value instanceof PageArray => $value->jsonSerialize(),
            $value instanceof Language => $value->toSummary(),
            $value instanceof Model && method_exists($value, 'toSummary') => $value->toSummary(),
            $value instanceof Model => $value->toArray(),
            is_array($value) => array_map($this->serialize(...), $value),
            is_object($value) && method_exists($value, 'toArray') => $value->toArray(),
            default => $value,
        };
    }

    /**
     * Refuse writes in languages outside config("lynx.agents.allowed_languages").
     *
     * @param  array<string, mixed>  $arguments
     */
    private function guardLanguages(array $arguments): void
    {
        $allowed = Value::strings(config('lynx.agents.allowed_languages', []));

        if ($allowed === []) {
            return;
        }

        $denied = array_values(array_diff(array_unique($this->languagesWritten($arguments)), $allowed));

        if ($denied !== []) {
            throw ValidationException::withMessages(['language' => 'This agent may not write in: '.implode(', ', $denied).'.']);
        }
    }

    /**
     * The language codes a tool call writes in: its language or target, its names, the
     * languages its values are keyed by, and the current language for unkeyed values.
     *
     * @param  array<string, mixed>  $arguments
     * @return list<string>
     */
    private function languagesWritten(array $arguments): array
    {
        $codes = [];

        foreach (['language', 'to'] as $key) {
            if (isset($arguments[$key]) && is_string($arguments[$key])) {
                $codes[] = $arguments[$key];
            }
        }

        foreach ((array) ($arguments['names'] ?? []) as $code => $_) {
            $codes[] = (string) $code;
        }

        foreach ((array) ($arguments['values'] ?? []) as $value) {
            if (is_array($value)) {
                foreach (array_keys($value) as $code) {
                    if (is_string($code) && $this->languages->find($code) !== null) {
                        $codes[] = $code;
                    }
                }
            }
        }

        if (! isset($arguments['language']) && isset($arguments['values'])) {
            $codes[] = $this->context->current()->code;
        }

        return $codes;
    }
}
