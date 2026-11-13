<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * The settings each field has on a template the site has (label, description, tab, width,
 * required...), kept when a draft only names the field there (ADR-0116), as labels and field
 * settings are kept. What the draft sets wins, and null clears a setting. Where a field sits is
 * the draft's to say, so its old section is never carried over.
 *
 * @internal
 */
final readonly class DraftUseSettings
{
    /** Settings that place a field, which the draft's own layout decides. */
    private const array PLACEMENT = ['section' => true];

    public function __construct(private TemplateRegistry $templates) {}

    /**
     * @param  list<mixed>  $entries  the draft's field list for the template
     * @return list<mixed>
     */
    public function merge(?Template $existing, array $entries): array
    {
        if (! $existing instanceof Template) {
            return $entries;
        }

        $current = $this->templates->fields($existing)
            ->map(fn (TemplateField $field): array => array_diff_key($field->settings, self::PLACEMENT))
            ->all();

        return $this->entries($entries, $current);
    }

    /**
     * @param  list<mixed>  $entries
     * @param  array<string, array<string, mixed>>  $current
     * @return list<mixed>
     */
    private function entries(array $entries, array $current): array
    {
        return array_map(fn (mixed $entry): mixed => $this->entry($entry, $current), $entries);
    }

    /** @param  array<string, array<string, mixed>>  $current */
    private function entry(mixed $entry, array $current): mixed
    {
        $entry = is_string($entry) ? ['name' => $entry] : Value::map($entry);

        if (isset($entry['section'])) {
            return array_replace($entry, ['fields' => $this->entries(Value::list($entry['fields'] ?? []), $current)]);
        }

        $name = Value::string($entry['name'] ?? '');
        $settings = array_filter(
            [...$current[$name] ?? [], ...array_diff_key($entry, ['name' => true])],
            fn (mixed $value): bool => $value !== null,
        );

        return $settings === [] ? $name : ['name' => $name] + $settings;
    }
}
