<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Choices;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Stringable;
use Traversable;

/**
 * The chosen options of a choice field that allows several, in the order of the field's options.
 * Printing it prints the labels, comma separated.
 *
 * @api
 *
 * @implements IteratorAggregate<int, Choice>
 */
final readonly class ChoiceList implements Countable, IteratorAggregate, Stringable
{
    /**
     * @param  list<Choice>  $choices
     */
    public function __construct(private array $choices) {}

    public function has(string $value): bool
    {
        return in_array($value, $this->values(), true);
    }

    /**
     * @return list<string>
     */
    public function values(): array
    {
        return array_map(fn (Choice $choice): string => $choice->value, $this->choices);
    }

    /**
     * @return list<string>
     */
    public function labels(): array
    {
        return array_map(fn (Choice $choice): string => $choice->label(), $this->choices);
    }

    public function first(): ?Choice
    {
        return $this->choices[0] ?? null;
    }

    public function count(): int
    {
        return count($this->choices);
    }

    /**
     * @return Traversable<int, Choice>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->choices);
    }

    public function __toString(): string
    {
        return implode(', ', $this->labels());
    }
}
