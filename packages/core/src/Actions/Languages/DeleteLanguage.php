<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Languages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Events\LanguageDeleted;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Removes a language and every value, name and path stored in it. The default language
 * can never be deleted. Languages that fell back to it fall back to the default instead.
 */
final class DeleteLanguage extends Action
{
    public function __construct(
        private readonly LanguageRegistry $languages,
        private readonly FieldRegistry $fields,
    ) {}

    public static function name(): string
    {
        return 'languages.delete';
    }

    public static function permission(): string
    {
        return 'schema.manage';
    }

    public static function description(): string
    {
        return 'Delete a language and all content stored in it (translated values, page names and paths). The default language cannot be deleted.';
    }

    public static function destructive(): bool
    {
        return true;
    }

    public static function rules(): array
    {
        return ['language' => ['required']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'language' => $schema->string()->description('Code of the language to delete.')->required(),
        ];
    }

    public function handle(Input $input, ActionContext $context): Language
    {
        $language = $this->languages->get(Value::string($input->get('language')));

        if ($language->id === $this->languages->default()->id) {
            throw ValidationException::withMessages(['language' => 'The default language cannot be deleted.']);
        }

        Language::query()->where('fallback_id', $language->id)->update(['fallback_id' => null]);

        $values = 0;

        foreach ($this->fields->translatable() as $field) {
            $values += DB::table(Tables::field($field->name))->where('language_id', $language->id)->delete();
        }

        $names = DB::table(Tables::PAGE_LANGUAGES)->where('language_id', $language->id)->delete();
        DB::table(Tables::PAGE_PATHS)->where('language_id', $language->id)->delete();
        DB::table(Tables::FIELD_VALUE_META)->where('language_id', $language->id)->delete();
        DB::table(Tables::SCHEDULES)->where('language_id', $language->id)->delete();
        Language::query()->whereKey($language->id)->delete();
        $this->languages->flush();

        $context->changes->deleted('language', $language->id, "Delete language {$language->code}, {$values} translated values and {$names} page names", [
            'code' => $language->code,
            'values' => $values,
            'page_names' => $names,
        ]);
        $context->event(new LanguageDeleted($language));

        return $language;
    }
}
