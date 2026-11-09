<?php

declare(strict_types=1);

namespace LynxCms\Core\Doctor\Checks;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Doctor\Check;
use LynxCms\Core\Doctor\Finding;
use LynxCms\Core\Doctor\Severity;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Fields\Items\Subfield;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Fields no template uses, directly or inside a repeater's or matrix's items: left over from a
 * removed template or added and never placed.
 *
 * @internal
 */
final readonly class UnusedFields implements Check
{
    public function __construct(private FieldRegistry $fields) {}

    public function key(): string
    {
        return 'unused-fields';
    }

    public function label(): string
    {
        return 'Every field is on a template';
    }

    public function run(): array
    {
        $onTemplates = DB::table(Tables::TEMPLATE_FIELDS)->distinct()->pluck('field_id')->map(Value::int(...))->all();
        $inItems = $this->subfieldNames();
        $unused = $this->fields->all()
            ->reject(fn (Field $field): bool => in_array((int) $field->id, $onTemplates, true) || in_array($field->name, $inItems, true))
            ->map(fn (Field $field): string => (string) $field->name)->sort()->values()->all();

        return $unused === [] ? [] : [new Finding(
            Severity::Warning,
            'No template uses '.implode(', ', array_map(fn (string $name): string => '['.$name.']', $unused)).'.',
            'Add them to a template, or delete them with fields.delete (their values go too).',
        )];
    }

    /**
     * Fields used inside the items of repeaters and matrices.
     *
     * @return list<string>
     */
    private function subfieldNames(): array
    {
        $names = [];

        foreach ($this->fields->all() as $field) {
            $type = $field->fieldType();

            if ($type instanceof ItemFieldType) {
                array_push($names, ...array_map(fn (Subfield $subfield): string => $subfield->name(), $type->allSubfields($field->config())));
            }
        }

        return $names;
    }
}
