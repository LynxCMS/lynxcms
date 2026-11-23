<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\References;

use LynxCms\Core\Support\Value;

/**
 * How one page's rows in the page references index differ from what its stored values hold.
 *
 * @internal
 */
final readonly class ReferenceChanges
{
    /**
     * @param  list<PageReference>  $added
     * @param  array<int, PageReference>  $removed  row id => reference
     * @param  array<int, int>  $resorted  row id => new sort
     */
    public function __construct(
        public array $added = [],
        public array $removed = [],
        public array $resorted = [],
    ) {}

    /**
     * Pair stored rows with expected references of the same identity, in order; what is left over
     * on either side was added or removed.
     *
     * @param  list<PageReference>  $expected
     * @param  iterable<object>  $rows  stored rows, each with an id
     */
    public static function between(array $expected, iterable $rows): self
    {
        $stored = [];

        foreach ($rows as $row) {
            $reference = PageReference::fromRow($row);
            $stored[$reference->identity()][] = [Value::int($row->id ?? null), $reference];
        }

        $added = [];
        $resorted = [];

        foreach ($expected as $reference) {
            $match = isset($stored[$reference->identity()]) ? array_shift($stored[$reference->identity()]) : null;

            if ($match === null) {
                $added[] = $reference;
            } elseif ($match[1]->sort !== $reference->sort) {
                $resorted[$match[0]] = $reference->sort;
            }
        }

        $removed = [];

        foreach ($stored as $left) {
            foreach ($left as [$id, $reference]) {
                $removed[$id] = $reference;
            }
        }

        return new self($added, $removed, $resorted);
    }

    public function isEmpty(): bool
    {
        return $this->added === [] && $this->removed === [] && $this->resorted === [];
    }

    /** Whether references were added or removed, beyond a new order. */
    public function changesReferences(): bool
    {
        return $this->added !== [] || $this->removed !== [];
    }
}
