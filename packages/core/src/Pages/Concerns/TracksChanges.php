<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\Concerns;

/**
 * What changed on a page since it was loaded, for PageLoader and the page actions: only changed
 * values and names are written. Part of Page, kept apart to keep that class small.
 *
 * @internal
 */
trait TracksChanges
{
    /** The page whose values this copy shows (withValuesOf), so its strings show too. */
    private ?int $contentOf = null;

    /**
     * @param  array<string, array<int, mixed>>  $values
     * @param  array<int, array{name: string, status: string}>  $names
     *
     * @internal
     */
    public function hydrate(array $values, array $names): static
    {
        $this->values = $values;
        $this->names = $names;
        $this->dirtyValues = [];
        $this->dirtyNames = [];
        $this->dirtyAttributes = false;

        return $this;
    }

    /**
     * A copy of this page that shows another page's values and template strings, e.g. its draft
     * for a preview (ADR-0039, ADR-0048). Names, URLs and status stay this page's.
     *
     * @internal
     */
    public function withValuesOf(self $other): static
    {
        $other->loadAllDeferred();
        $copy = clone $this;
        $copy->forgetDeferred();
        $copy->values = $other->values;
        $copy->dirtyValues = [];
        $copy->contentOf = $other->id;

        return $copy;
    }

    /**
     * The id the page's own template strings are stored under: its own, or the draft's in a preview.
     *
     * @internal
     */
    public function contentId(): ?int
    {
        return $this->contentOf ?? $this->id;
    }

    /**
     * @return array<string, array<int, true>>
     *
     * @internal
     */
    public function dirtyValues(): array
    {
        return $this->dirtyValues;
    }

    /**
     * @return array<int, true>
     *
     * @internal
     */
    public function dirtyNames(): array
    {
        return $this->dirtyNames;
    }

    public function isDirty(): bool
    {
        return $this->id === null || $this->dirtyValues !== [] || $this->dirtyNames !== [] || $this->dirtyAttributes;
    }

    /** @internal */
    public function markClean(): void
    {
        $this->dirtyValues = [];
        $this->dirtyNames = [];
        $this->dirtyAttributes = false;
    }

    /**
     * @return array<string, array<int, mixed>>
     *
     * @internal
     */
    public function rawValues(): array
    {
        $this->loadAllDeferred();

        return $this->values;
    }

    /**
     * @return array<int, array{name: string, status: string}>
     *
     * @internal
     */
    public function rawNames(): array
    {
        return $this->names;
    }

    /** @internal */
    public function forgetField(string $field): void
    {
        unset($this->deferredFields[$field]);
        unset($this->values[$field], $this->dirtyValues[$field]);
    }
}
