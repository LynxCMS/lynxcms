<?php

declare(strict_types=1);

namespace LynxCms\Core\Doctor\Checks;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Doctor\Check;
use LynxCms\Core\Doctor\Finding;
use LynxCms\Core\Doctor\Severity;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Values kept for pages whose template no longer has the field (removed from the template, or the
 * page switched templates). Lynx never deletes them by itself (AGENTS rule 6); they only take space.
 *
 * @internal
 */
final readonly class OrphanedValues implements Check
{
    public function __construct(private FieldRegistry $fields, private TemplateRegistry $templates) {}

    public function key(): string
    {
        return 'orphaned-values';
    }

    public function label(): string
    {
        return 'No values are kept for fields a page\'s template no longer has';
    }

    public function run(): array
    {
        $findings = [];

        foreach ($this->fields->all()->sortBy('name') as $field) {
            foreach ($this->orphans($field) as $templateId => $count) {
                $template = $this->templates->find($templateId)->name ?? (string) $templateId;
                $findings[] = new Finding(
                    Severity::Warning,
                    "Pages using [{$template}] keep {$count} value(s) of [{$field->name}], which the template no longer has.",
                    "Add [{$field->name}] back to the template, or purge them with pages.purge_orphans (template: {$template}).",
                );
            }
        }

        return $findings;
    }

    /**
     * @return array<int, int> template id => values kept for it
     */
    private function orphans(Field $field): array
    {
        $holders = DB::table(Tables::TEMPLATE_FIELDS)->where('field_id', $field->id)->pluck('template_id')->all();
        $rows = DB::table($field->table())
            ->join(Tables::PAGES, Tables::PAGES.'.id', '=', $field->table().'.page_id')
            ->whereNotIn(Tables::PAGES.'.template_id', $holders)
            ->groupBy(Tables::PAGES.'.template_id')
            ->select(Tables::PAGES.'.template_id')
            ->selectRaw('count(*) as orphans')
            ->pluck('orphans', 'template_id');

        $orphans = [];

        foreach ($rows as $templateId => $count) {
            $orphans[(int) $templateId] = Value::int($count);
        }

        return $orphans;
    }
}
