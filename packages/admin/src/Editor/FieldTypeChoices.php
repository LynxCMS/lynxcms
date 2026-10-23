<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

/**
 * The field types the field form offers for a new field. Modules hide types that only their own
 * schema creates: app(FieldTypeChoices::class)->exclude('options'). Hidden types still work
 * through the API and MCP, and existing fields of those types still open in the field form.
 *
 * @api
 */
final class FieldTypeChoices
{
    /** @var array<string, true> */
    private array $excluded = [];

    public function exclude(string ...$types): void
    {
        $this->excluded += array_fill_keys($types, true);
    }

    public function offers(string $type): bool
    {
        return ! isset($this->excluded[$type]);
    }

    /**
     * @template T
     *
     * @param  array<string, T>  $types  type name => anything, e.g. lynx()->fields->types()
     * @return array<string, T> the offered ones
     */
    public function for(array $types): array
    {
        return array_diff_key($types, $this->excluded);
    }
}
