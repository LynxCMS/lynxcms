<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Runner;

use UnexpectedValueException;

/**
 * What the runner returns: the action's return value, the changes and whether it was a dry run.
 *
 * @api
 */
final readonly class ActionResult
{
    public function __construct(
        public string $action,
        public mixed $value,
        public ChangeSet $changes,
        public bool $dryRun,
    ) {}

    /**
     * The return value, checked against the class the action is documented to return.
     *
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     */
    public function valueAs(string $class): object
    {
        return $this->value instanceof $class
            ? $this->value
            : throw new UnexpectedValueException("{$this->action} returned ".get_debug_type($this->value).", expected {$class}.");
    }

    /**
     * The result itself for a dry run (nothing was written), otherwise the typed return value.
     *
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T|self
     */
    public function valueOrDryRun(string $class): object
    {
        return $this->dryRun ? $this : $this->valueAs($class);
    }

    /**
     * @return array<mixed>
     */
    public function arrayValue(): array
    {
        return is_array($this->value)
            ? $this->value
            : throw new UnexpectedValueException("{$this->action} returned ".get_debug_type($this->value).', expected an array.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'dry_run' => $this->dryRun,
            'destructive' => $this->changes->isDestructive(),
            'changes' => $this->changes->toArray(),
        ];
    }
}
