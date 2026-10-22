<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Runner;

use Countable;

/**
 * The changes recorded during one outer action, including nested actions.
 *
 * @api
 */
final class ChangeSet implements Countable
{
    /** @var list<Change> */
    private array $changes = [];

    public function add(Change $change): Change
    {
        return $this->changes[] = $change;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function created(string $subject, int|string|null $id, string $summary, array $data = []): Change
    {
        return $this->add(new Change(Change::CREATED, $subject, $id, $summary, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updated(string $subject, int|string|null $id, string $summary, array $data = []): Change
    {
        return $this->add(new Change(Change::UPDATED, $subject, $id, $summary, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function deleted(string $subject, int|string|null $id, string $summary, array $data = []): Change
    {
        return $this->add(new Change(Change::DELETED, $subject, $id, $summary, $data, destructive: true));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function schema(string $subject, string $summary, array $data = [], bool $destructive = false): Change
    {
        return $this->add(new Change(Change::SCHEMA, $subject, null, $summary, $data, $destructive));
    }

    /**
     * @return list<Change>
     */
    public function all(): array
    {
        return $this->changes;
    }

    public function isDestructive(): bool
    {
        return array_any($this->changes, fn (Change $change): bool => $change->destructive);
    }

    public function count(): int
    {
        return count($this->changes);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(fn (Change $change): array => $change->toArray(), $this->changes);
    }

    /**
     * Human-readable lines, one per change, for plans and CLI output.
     *
     * @return list<string>
     */
    public function summary(): array
    {
        return array_map(
            fn (Change $c): string => ($c->destructive ? '! ' : '+ ').$c->summary,
            $this->changes,
        );
    }
}
