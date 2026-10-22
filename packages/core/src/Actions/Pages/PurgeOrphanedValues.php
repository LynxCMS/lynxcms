<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Pages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Pages\Concerns\ResolvesPages;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * The only action that deletes field values left behind by template changes (ADR-0012).
 */
final class PurgeOrphanedValues extends Action
{
    use ResolvesPages;

    public function __construct(
        private readonly FieldRegistry $fields,
        private readonly TemplateRegistry $templates,
    ) {}

    public static function name(): string
    {
        return 'pages.purge_orphans';
    }

    public static function permission(): string
    {
        return 'schema.manage';
    }

    public static function description(): string
    {
        return 'Delete values of fields that are no longer on a page\'s template, for one page or for every page of a template.';
    }

    public static function destructive(): bool
    {
        return true;
    }

    public static function rules(): array
    {
        return ['page' => ['nullable'], 'template' => ['nullable', 'string']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->string()->description('Id or path of one page.'),
            'template' => $schema->string()->description('Template name: purge every page using it.'),
        ];
    }

    /**
     * @return array<string, int> field => rows deleted
     */
    public function handle(Input $input, ActionContext $context): array
    {
        $byTemplate = [];

        if ($input->get('page') !== null) {
            $page = $this->resolvePageReference($input->get('page'));
            $byTemplate[$page->templateId] = [(int) $page->id];
        } elseif (($name = $input->string('template')) !== null) {
            $template = $this->templates->get($name);
            $byTemplate[$template->id] = DB::table(Tables::PAGES)->where('template_id', $template->id)->pluck('id')->map(fn ($id): int => Value::int($id))->all();
        } else {
            throw ValidationException::withMessages(['page' => 'Give a page or a template.']);
        }

        $deleted = [];

        foreach ($byTemplate as $templateId => $pageIds) {
            $keep = $this->templates->fields($templateId)->keys()->all();

            foreach ($this->fields->all() as $field) {
                if (in_array($field->name, $keep, true)) {
                    continue;
                }

                $count = DB::table($field->table())->whereIn('page_id', $pageIds)->delete();
                DB::table(Tables::FIELD_VALUE_META)->where('field_id', $field->id)->whereIn('page_id', $pageIds)->delete();

                if ($count > 0) {
                    $deleted[$field->name] = ($deleted[$field->name] ?? 0) + $count;
                }
            }
        }

        foreach ($deleted as $field => $count) {
            $context->changes->deleted('value', null, "Purge {$count} orphaned value(s) of {$field}", ['field' => $field, 'rows' => $count]);
        }

        return $deleted;
    }
}
