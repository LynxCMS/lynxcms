<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use LynxCms\Core\Support\Value;

/**
 * The field form's formatters (ADR-0031): switches that turn each one on or off, and the order
 * the ones on run in. Only formatters registered for the field's type can be on.
 *
 * @internal
 */
trait EditsFormatters
{
    /** @var list<string> text formatters in the order they run */
    public array $formatters = [];

    /** A switch: on adds the formatter at the end of the order, off removes it. */
    public function toggleFormatter(string $name): void
    {
        in_array($name, $this->formatters, true) ? $this->removeFormatter($name) : $this->addFormatter($name);
    }

    public function addFormatter(string $name): void
    {
        if (! in_array($name, $this->formatters, true) && array_key_exists($name, lynx()->formatters()->forType($this->type))) {
            $this->formatters[] = $name;
        }
    }

    public function removeFormatter(string $name): void
    {
        $this->formatters = array_values(array_diff($this->formatters, [$name]));
    }

    /** wire:sort handler: a formatter dropped at a 0-based position. */
    public function sortFormatter(string $name, int|string $position): void
    {
        if (in_array($name, $this->formatters, true)) {
            $order = array_values(array_diff($this->formatters, [$name]));
            array_splice($order, max(0, Value::int($position)), 0, [$name]);
            $this->formatters = $order;
        }
    }
}
