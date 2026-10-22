<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Languages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Events\LanguagePrefixChanged;
use LynxCms\Core\Events\LanguageUpdated;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Schema\LocalizedSchemaText;
use LynxCms\Core\Support\Value;

/**
 * Renames a language or changes its code, locale, fallback, prefix or active state.
 * Rejects fallback cycles.
 */
final class UpdateLanguage extends Action
{
    public function __construct(
        private readonly LanguageRegistry $languages,
        private readonly LocalizedSchemaText $schemaText,
    ) {}

    public static function name(): string
    {
        return 'languages.update';
    }

    public static function permission(): string
    {
        return 'schema.manage';
    }

    public static function description(): string
    {
        return 'Change a language: code, name, tab label, locale, fallback language, URL prefix or whether it is active. Fallback cycles are rejected.';
    }

    public static function rules(): array
    {
        return [
            'language' => ['required'],
            'code' => ['sometimes', 'string', 'regex:/^[a-z0-9][a-z0-9_-]{0,31}$/'],
            'name' => ['sometimes', 'string', 'max:255'],
            'label' => ['sometimes', 'nullable', 'string', 'max:32'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:32'],
            'fallback' => ['sometimes', 'nullable', 'string'],
            'prefix' => ['sometimes', 'nullable', 'string', 'regex:/^[a-z0-9-]*$/'],
            'active' => ['sometimes', 'boolean'],
            'provider_codes' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'language' => $schema->string()->description('Code of the language to change.')->required(),
            'code' => $schema->string()->description('New code.'),
            'name' => $schema->string()->description('New display name.'),
            'label' => $schema->string()->description('New label for the admin\'s language tabs, or empty to show the name.'),
            'locale' => $schema->string()->description('New locale, e.g. "en-GB".'),
            'fallback' => $schema->string()->description('Code of the new fallback language, or empty for none (falls back to the default language).'),
            'prefix' => $schema->string()->description('New URL prefix. Empty string means no prefix.'),
            'active' => $schema->boolean()->description('Whether the language is visible on the front end.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Language
    {
        $language = $this->languages->get(Value::string($input->get('language')));
        $before = $language->only(['code', 'name', 'label', 'locale', 'fallback_id', 'url_prefix', 'is_active']);
        $attributes = [];

        foreach (['code' => 'code', 'name' => 'name', 'label' => 'label', 'locale' => 'locale', 'prefix' => 'url_prefix', 'active' => 'is_active', 'provider_codes' => 'provider_codes'] as $key => $column) {
            if ($input->has($key)) {
                $attributes[$column] = match ($key) {
                    'prefix' => Value::string($input->get($key)),
                    'label' => $input->string($key) ?: null,
                    default => $input->get($key),
                };
            }
        }

        if ($input->has('fallback')) {
            $attributes['fallback_id'] = $this->resolveFallback($language, $input->string('fallback'));
        }

        foreach (['code' => 'code', 'url_prefix' => 'prefix'] as $column => $key) {
            if (isset($attributes[$column]) && Language::query()->where($column, $attributes[$column])->whereKeyNot($language->id)->exists()) {
                throw ValidationException::withMessages([$key => "The {$key} [".Value::string($attributes[$column]).'] is already used by another language.']);
            }
        }

        $model = Language::query()->findOrFail($language->id);
        $model->fill($attributes)->save();
        $this->languages->flush();

        $diff = array_diff_assoc(array_map(strval(...), $model->only(array_keys($before))), array_map(strval(...), $before));
        $context->changes->updated('language', $model->id, "Update language {$model->code}: ".implode(', ', array_keys($diff)), $diff);

        if (isset($diff['code'])) {
            // Schema text keyed by language code follows the rename (ADR-0043).
            $this->schemaText->renameLanguage(Value::string($before['code']), (string) $model->code);
        }

        if ($diff !== []) {
            $context->event(new LanguageUpdated($model, array_map(strval(...), array_keys($diff))));
        }

        if (isset($diff['url_prefix'])) {
            // Paths for this language are rebuilt from phase 3 on (RebuildPaths listens for this).
            $context->event(new LanguagePrefixChanged($model));
        }

        return $model;
    }

    private function resolveFallback(Language $language, ?string $code): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }

        if ($language->id === $this->languages->default()->id) {
            throw ValidationException::withMessages(['fallback' => 'The default language is the end of every fallback chain and cannot fall back to another language.']);
        }

        $fallback = $this->languages->get($code);

        if ($fallback->id === $language->id) {
            throw ValidationException::withMessages(['fallback' => 'A language cannot fall back to itself.']);
        }

        // Walk the new fallback's chain; reaching this language means a cycle.
        $current = $fallback;
        $seen = [];

        while ($current !== null && ! isset($seen[$current->id])) {
            if ($current->id === $language->id) {
                throw ValidationException::withMessages(['fallback' => "Falling back to {$fallback->code} would create a cycle."]);
            }

            $seen[$current->id] = true;
            $current = $this->languages->find($current->fallback_id);
        }

        return $fallback->id;
    }
}
