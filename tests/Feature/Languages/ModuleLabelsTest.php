<?php

declare(strict_types=1);

use LynxCms\Cache\Actions\InstallPageCache;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Languages\EditorLanguage;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Schema\SchemaWording;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Forms\Actions\InstallForms;
use LynxCms\Forms\FormSchema;
use LynxCms\Menus\Actions\InstallMenus;
use LynxCms\PagePassword\Actions\EnablePagePassword;
use LynxCms\Seo\Actions\EnableSeo;
use LynxCms\Seo\SeoSchema;
use LynxCms\Sitemap\Actions\InstallSitemap;

/*
 * The labels, descriptions and notes Lynx and its modules install read in the languages Lynx ships
 * (ADR-0144): lang/{locale}/schema.php has every text of the installed schema, and no other.
 */

beforeEach(function (): void {
    courseSite();
    collect([InstallMenus::class, InstallForms::class, InstallSitemap::class, InstallPageCache::class])->each(fn (string $action): ActionResult => lynx()->run($action));
    lynx()->run(EnableSeo::class, ['template' => 'course']);
    lynx()->run(EnablePagePassword::class, ['template' => 'course']);
    // The trash page is made with the first page it takes.
    createCourses('gone');
    lynx()->pages->trash('/courses/gone/');
});

/** Keys of settings that hold text for editors. */
const TEXT_KEYS = ['label', 'description', 'notes', 'placeholder'];

/**
 * The text for editors anywhere in a field's or template field's settings.
 *
 * @return list<string>
 */
function textsIn(mixed $settings): array
{
    $texts = [];

    foreach (Value::array($settings) as $key => $value) {
        $texts = [...$texts, ...(in_array($key, TEXT_KEYS, true) && is_string($value) ? [$value] : textsIn($value))];
    }

    return array_values(array_filter($texts));
}

/** @return array<string, list<string>> every text the installed schema shows editors, by the translation namespace it belongs to */
function installedTexts(): array
{
    $wording = app(SchemaWording::class);
    $texts = [];
    $add = function (?string $namespace, string ...$found) use (&$texts): void {
        if ($namespace !== null) {
            $texts[$namespace] = [...$texts[$namespace] ?? [], ...$found];
        }
    };

    lynx()->fields->all()->each(fn (Field $field) => $add($wording->ofField($field->name), Value::string($field->labels()->toStorage()), ...textsIn($field->config())));

    foreach (lynx()->templates->all() as $template) {
        $add($wording->ofTemplate((string) $template->name), Value::string(LocalizedText::from($template->getRawOriginal('label'))->toStorage()));
        lynx()->templates->fields($template)->each(fn (TemplateField $field) => $add($wording->ofField($field->name()), ...textsIn($field->settings)));
    }

    return array_map(function (array $found): array {
        $unique = array_values(array_unique(array_filter($found)));
        sort($unique);

        return $unique;
    }, $texts);
}

it('translates every text it installs, and nothing else', function (string $locale): void {
    $texts = installedTexts();

    expect(array_keys($texts))->toEqualCanonicalizing(['lynx', 'lynx-menus', 'lynx-forms', 'lynx-seo', 'lynx-sitemap', 'lynx-cache', 'lynx-page-password']);

    foreach ($texts as $namespace => $installed) {
        $lines = Value::array(Lang::get("{$namespace}::schema", [], $locale, false));
        $keys = array_map(strval(...), array_keys($lines));
        sort($keys);

        expect($keys)->toBe($installed, "{$namespace} in {$locale}")
            ->and(array_filter($lines, fn (mixed $line): bool => ! is_string($line) || $line === ''))->toBe([]);
    }
})->with(['pt', 'es']);

it('reads a module\'s labels in the editor\'s language', function (): void {
    app()->setLocale('pt');
    app(EditorLanguage::class)->use('pt');
    $lines = Value::map(Lang::get('lynx-seo::schema', [], 'pt', false));

    expect(lynx()->fields->get(SeoSchema::NOINDEX)->label)->toBe($lines['Hide from search engines'])
        ->and(lynx()->templates->fields('course')->get(SeoSchema::IMAGE)?->description())->toBe($lines['Shown when the page is shared, cropped to 1200 by 630 pixels.'])
        ->and(lynx()->templates->get(FormSchema::FORM_TEMPLATE)->label)->toBe(__('lynx-forms::schema.Form', [], 'pt'))
        ->and(lynx()->fields->get('title')->label)->toBe('Título');
});

it('leaves what visitors read in the content language', function (): void {
    expect(lynx()->fields->get(SeoSchema::NOINDEX)->label)->toBe('Hide from search engines');
});
