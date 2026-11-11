<?php

declare(strict_types=1);

namespace LynxCms\Finder\Catalog;

/**
 * Something a finder condition can look at: a page property or a field. $name is what the
 * selector calls it; $options are the values a choice offers (template and status), by value.
 *
 * @api
 */
final readonly class Subject
{
    /**
     * @param  array<string, string>  $options
     */
    public function __construct(
        public string $key,
        public string $label,
        public Kind $kind,
        public string $name,
        public array $options = [],
    ) {}

    public function allows(Operator $operator): bool
    {
        return in_array($operator, $this->kind->operators(), true);
    }
}
