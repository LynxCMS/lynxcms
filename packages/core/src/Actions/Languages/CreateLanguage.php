<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Languages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Events\LanguageCreated;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Models\Setting;
use LynxCms\Core\Pages\RootPage;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Adds a language at runtime. One INSERT, no schema change (ADR-0003).
 * The first language created becomes the default.
 */
final class CreateLanguage extends Action
{
    public function __construct(
        private readonly LanguageRegistry $languages,
        private readonly RootPage $root,
    ) {}

    public static function name(): string
    {
        return 'languages.create';
    }

    public static function permission(): string
    {
        return 'schema.manage';
    }

    public static function description(): string
    {
        return 'Add a content language at runtime. Optionally set its tab label, locale, the language it falls back to when a value is missing, and its URL prefix (defaults to the code).';
    }

    public static function rules(): array
    {
        return [
            'uuid' => ['nullable', 'uuid', Rule::unique(Tables::LANGUAGES, 'uuid')],
            'code' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9_-]{0,31}$/', Rule::unique(Tables::LANGUAGES, 'code')],
            'name' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:32'],
            'locale' => ['nullable', 'string', 'max:32'],
            'fallback' => ['nullable', 'string'],
            'prefix' => ['nullable', 'string', 'regex:/^[a-z0-9-]*$/', Rule::unique(Tables::LANGUAGES, 'url_prefix')],
            'active' => ['sometimes', 'boolean'],
            'provider_codes' => ['nullable', 'array'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->description('Short code, e.g. "pt" or "pt-br". Lowercase letters, digits, "-" and "_".')->required(),
            'name' => $schema->string()->description('Display name, e.g. "Português".')->required(),
            'label' => $schema->string()->description('Short label for the admin\'s language tabs, e.g. "PT". Defaults to the name.'),
            'locale' => $schema->string()->description('Locale for Laravel and HTML, e.g. "pt-PT". Defaults to the code.'),
            'fallback' => $schema->string()->description('Code of the language to fall back to when a value is missing. Defaults to the default language.'),
            'prefix' => $schema->string()->description('URL prefix, e.g. "pt". Defaults to the code. Empty string means no prefix.'),
            'active' => $schema->boolean()->description('Whether the language is visible on the front end. Default true.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Language
    {
        $isFirst = ! $this->languages->hasDefault();
        $fallback = $input->string('fallback') !== null ? $this->languages->get($input->string('fallback')) : null;
        $prefix = $input->has('prefix') ? (string) $input->string('prefix') : ($isFirst ? '' : $input->string('code'));

        $language = Language::query()->create([
            'uuid' => $input->string('uuid') ?? (string) Str::uuid(),
            'code' => $input->string('code'),
            'name' => $input->string('name'),
            'label' => $input->string('label') ?: null,
            'locale' => $input->string('locale'),
            'fallback_id' => $fallback?->id,
            'url_prefix' => $prefix,
            'provider_codes' => $input->get('provider_codes'),
            'is_active' => $input->bool('active', true),
            'sort' => Value::int(Language::query()->max('sort')) + 1,
        ]);

        if ($isFirst) {
            Setting::write(LanguageRegistry::DEFAULT_SETTING, (string) $language->id);
        }

        $this->languages->flush();

        $context->changes->created('language', $language->id, "Create language {$language->code} ({$language->name})", [
            'code' => $language->code,
            'prefix' => $language->url_prefix,
            'fallback' => $fallback?->code,
            'default' => $isFirst,
        ]);
        $context->event(new LanguageCreated($language));
        $this->activateHomePage($language, $context);

        return $language;
    }

    /**
     * The home page has no name to translate, so nothing else would make it active in a language
     * added after install: without this, "/pt/" answered 404 while every page below it worked.
     */
    private function activateHomePage(Language $language, ActionContext $context): void
    {
        $home = $this->root->id();

        if ($home !== null) {
            $context->run(SavePage::class, ['id' => $home, 'names' => [$language->code => ['active' => true]]]);
        }
    }
}
