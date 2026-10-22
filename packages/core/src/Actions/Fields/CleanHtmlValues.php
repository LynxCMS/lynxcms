<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Fields;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Change;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Models\Field;
use stdClass;

/**
 * Runs the stored values of html fields through their field type again, so HTML saved before the
 * sanitizer (or before a stricter one) is cleaned the way a save cleans it today. Only values that
 * change are written.
 */
final class CleanHtmlValues extends Action
{
    private const int CHUNK = 500;

    public function __construct(private readonly FieldRegistry $fields) {}

    public static function name(): string
    {
        return 'fields.clean_html';
    }

    public static function permission(): string
    {
        return 'schema.manage';
    }

    public static function description(): string
    {
        return 'Clean the stored values of html fields as a save would (removes scripts, event attributes and other markup the editor does not produce). '
            .'Give "field" for one field; by default every html field. Dry-run first: it reports how many values each field would change.';
    }

    /** Markup that is removed cannot be restored. */
    public static function destructive(): bool
    {
        return true;
    }

    public static function rules(): array
    {
        return ['field' => ['nullable', 'string']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['field' => $schema->string()->description('Name of one html field. Default: every html field.')];
    }

    /**
     * @return array<string, int> field => values changed
     */
    public function handle(Input $input, ActionContext $context): array
    {
        $cleaned = [];

        foreach ($this->htmlFields($input->string('field')) as $field) {
            $count = $this->clean($field);

            if ($count > 0) {
                $cleaned[$field->name] = $count;
                $context->changes->add(new Change(Change::UPDATED, 'field', $field->id, "Clean {$count} value(s) of {$field->name}", [
                    'field' => $field->name,
                    'values' => $count,
                ], destructive: true));
            }
        }

        return $cleaned;
    }

    /**
     * @return Collection<int, Field>
     */
    private function htmlFields(?string $name): Collection
    {
        $isHtml = fn (Field $field): bool => $field->fieldType()->isHtml($field->config());

        if ($name === null) {
            return $this->fields->all()->filter($isHtml)->values();
        }

        $field = $this->fields->find($name) ?? throw ValidationException::withMessages(['field' => "Field [{$name}] does not exist."]);

        return $isHtml($field) ? collect([$field]) : throw ValidationException::withMessages(['field' => "Field [{$name}] is not an html field."]);
    }

    /** Rows are read in key order; only "data" changes, so the order stays stable while writing. */
    private function clean(Field $field): int
    {
        $count = 0;
        $keys = $field->isTranslatable() ? ['page_id', 'language_id'] : ['page_id'];
        $query = DB::table($field->table())->select([...$keys, 'data'])->whereNotNull('data');

        foreach ($keys as $key) {
            $query->orderBy($key);
        }

        $query->chunk(self::CHUNK, function (Collection $rows) use ($field, $keys, &$count): void {
            foreach ($rows as $row) {
                $count += $this->cleanRow($field, $keys, $row) ? 1 : 0;
            }
        });

        return $count;
    }

    /**
     * @param  list<string>  $keys
     */
    private function cleanRow(Field $field, array $keys, stdClass $row): bool
    {
        $stored = $row->data;
        $clean = $field->fieldType()->normalize($stored, $field->config());

        if ($clean === $stored) {
            return false;
        }

        $where = [];
        foreach ($keys as $key) {
            $where[$key] = $row->{$key};
        }

        DB::table($field->table())->where($where)->update(['data' => $clean]);

        return true;
    }
}
