<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Fields;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\FieldSchemaManager;
use LynxCms\Core\Fields\Items\SubfieldLanguageRows;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Search\SearchIndex;
use LynxCms\Core\Support\Value;
use Override;

/**
 * Makes a shared field translatable (ADR-0118): each value, on pages, their drafts and the items
 * of repeaters and matrices that show the field, becomes the default language's, which other
 * languages fall back to. Nothing is lost, so there is no action for the other way.
 */
final class MakeFieldTranslatable extends Action
{
    public function __construct(
        private readonly FieldRegistry $fields,
        private readonly FieldSchemaManager $schema,
        private readonly SubfieldLanguageRows $items,
        private readonly LanguageRegistry $languages,
        private readonly SearchIndex $search,
    ) {}

    public static function name(): string
    {
        return 'fields.make_translatable';
    }

    public static function permission(): string
    {
        return 'schema.manage';
    }

    #[Override]
    public static function changesSchema(): bool
    {
        return true;
    }

    public static function description(): string
    {
        return 'Make a shared field translatable. Each value becomes the default language\'s, which other languages fall back to until translated. Only text, textarea and URL fields can be translatable, and a translatable field cannot be made shared again.';
    }

    public static function rules(): array
    {
        return ['field' => ['required', 'string']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['field' => $schema->string()->description('Name of the shared field to make translatable.')->required()];
    }

    public function handle(Input $input, ActionContext $context): Field
    {
        $field = Field::query()->findOrFail($this->fields->get((string) $input->string('field'))->id);
        $this->assertConvertible($field);

        $field->scope = Field::TRANSLATABLE;
        $field->save();
        $this->fields->flush();

        $language = $this->languages->default()->id;
        $this->schema->makeTranslatable($field, $language);
        $this->reindex($this->convertItems($field, $language) + $this->pagesWithValues($field));

        $context->changes->schema('field', "Make field {$field->name} translatable", $field->toSummary());

        return $field;
    }

    private function assertConvertible(Field $field): void
    {
        if ($field->isTranslatable()) {
            throw ValidationException::withMessages(['field' => "Field [{$field->name}] is translatable already."]);
        }

        if (! $field->fieldType()->supportsTranslation()) {
            throw ValidationException::withMessages(['field' => "Fields of type [{$field->type}] cannot be translatable."]);
        }
    }

    /** @return array<int, true> pages whose items changed, by id */
    private function convertItems(Field $field, int $language): array
    {
        $pages = [];

        foreach ($this->fields->itemFieldsUsing($field->name) as $itemField) {
            $pages += array_fill_keys($this->items->moveToLanguage($this->fields->get($itemField), $field->name, $language), true);
        }

        return $pages;
    }

    /** @return array<int, true> */
    private function pagesWithValues(Field $field): array
    {
        return array_fill_keys(DB::table($field->table())->distinct()->pluck('page_id')->map(fn (mixed $id): int => Value::int($id))->all(), true);
    }

    /** @param  array<int, true>  $pages */
    private function reindex(array $pages): void
    {
        foreach (array_keys($pages) as $pageId) {
            $this->search->update($pageId);
        }
    }
}
