<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\Concerns;

use LynxCms\Core\Pages\DeferredFields;

/**
 * Fields a page was loaded without (ADR-0067): every method that reads or writes a value loads the
 * field first, for the whole batch the page came in. Part of Page, kept apart to keep that class small.
 *
 * @internal
 */
trait DefersFields
{
    private ?DeferredFields $deferredBatch = null;

    /** @var array<string, true> */
    private array $deferredFields = [];

    /**
     * @param  list<string>  $fields
     *
     * @internal
     */
    public function defer(DeferredFields $batch, array $fields): void
    {
        $this->deferredBatch = $fields === [] ? null : $batch;
        $this->deferredFields = array_fill_keys($fields, true);
    }

    /** @internal */
    public function isDeferred(string $field): bool
    {
        return isset($this->deferredFields[$field]);
    }

    /**
     * Values of a deferred field (language id => value), loaded by the batch.
     *
     * @param  array<int, mixed>  $values
     *
     * @internal
     */
    public function absorbDeferred(string $field, array $values): void
    {
        if (! isset($this->deferredFields[$field])) {
            return;
        }

        unset($this->deferredFields[$field]);

        if ($values !== []) {
            $this->values[$field] = $values;
        }

        if ($this->deferredFields === []) {
            $this->deferredBatch = null;
        }
    }

    /**
     * Stored values of one field in every language (language id => value), loading it if needed.
     *
     * @return array<int, mixed>
     *
     * @internal
     */
    public function rawValuesOf(string $field): array
    {
        $this->loadDeferred($field);

        return $this->values[$field] ?? [];
    }

    /** For a copy whose values all come from another page. */
    private function forgetDeferred(): void
    {
        $this->deferredBatch = null;
        $this->deferredFields = [];
    }

    private function loadDeferred(string $field): void
    {
        if (isset($this->deferredFields[$field]) && $this->deferredBatch instanceof DeferredFields) {
            $this->absorbDeferred($field, $this->deferredBatch->load($field, $this));
        }
    }

    private function loadAllDeferred(): void
    {
        foreach (array_keys($this->deferredFields) as $field) {
            $this->loadDeferred($field);
        }
    }
}
