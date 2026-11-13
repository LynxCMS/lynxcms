<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

/**
 * What building a draft changes (ADR-0116), shown before it runs: each step an action (create,
 * update, remove, write, keep, refresh), what it acts on and its name. Unchanged fields and templates
 * have no step.
 *
 * @api
 */
final readonly class DraftPlan
{
    public const string CREATE = 'create';

    public const string UPDATE = 'update';

    public const string WRITE = 'write';

    public const string KEEP = 'keep';

    public const string REFRESH = 'refresh';

    public const string REMOVE = 'remove';

    /**
     * @param  list<array{action: string, kind: string, name: string}>  $steps
     */
    public function __construct(public array $steps) {}

    /** @return list<string> such as "create field seats" */
    public function lines(): array
    {
        return array_map(fn (array $step): string => "{$step['action']} {$step['kind']} {$step['name']}", $this->steps);
    }

    /** Whether the draft creates or updates a field or template. */
    public function changesSchema(): bool
    {
        return array_filter($this->steps, fn (array $step): bool => in_array($step['action'], [self::CREATE, self::UPDATE], true)) !== [];
    }

    /** Whether the draft creates a page. */
    public function createsPages(): bool
    {
        return array_filter($this->steps, fn (array $step): bool => $step['action'] === self::CREATE && $step['kind'] === 'page') !== [];
    }

    /** Whether building changes anything: the schema or a page. */
    public function changesSite(): bool
    {
        return $this->changesSchema() || $this->createsPages();
    }

    /** @return list<string> names of the templates the draft creates */
    public function createdTemplates(): array
    {
        return array_values(array_map(
            fn (array $step): string => $step['name'],
            array_filter($this->steps, fn (array $step): bool => $step['action'] === self::CREATE && $step['kind'] === 'template'),
        ));
    }

    public function isEmpty(): bool
    {
        return $this->steps === [];
    }
}
