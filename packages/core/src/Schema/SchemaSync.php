<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Fields\DeleteField;
use LynxCms\Core\Actions\Fields\UpdateField;
use LynxCms\Core\Actions\Languages\CreateLanguage;
use LynxCms\Core\Actions\Languages\DeleteLanguage;
use LynxCms\Core\Actions\Languages\UpdateLanguage;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Actions\Templates\CreateTemplate;
use LynxCms\Core\Actions\Templates\DeleteTemplate;
use LynxCms\Core\Actions\Templates\UpdateTemplate;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Templates\TemplateRegistry;
use Throwable;

/**
 * Schema sync between environments (ADR-0014): languages, fields, templates and roles as YAML files
 * keyed by uuid. Apply diffs the files against the database and changes it through the normal
 * actions. Page content is not part of the schema.
 *
 * @phpstan-type LanguageItem array{uuid: string, code: string, name: string, label: string|null, locale: string|null, fallback: string|null, prefix: string, active: bool, default: bool, provider_codes: array<string, mixed>|null}
 * @phpstan-type FieldItem array{uuid: string, name: string, type: string, label: string|array<string, string>, translatable: bool, settings: array<string, mixed>}
 * @phpstan-type TemplateItem array{uuid: string, name: string, label: string|array<string, string>, settings: array<string, mixed>, fields: list<mixed>}
 * @phpstan-type SchemaFiles array{languages: list<LanguageItem>, fields: list<FieldItem>, templates: list<TemplateItem>}
 *
 * @api
 */
final readonly class SchemaSync
{
    public function __construct(
        private ActionRunner $runner,
        private LanguageRegistry $languages,
        private FieldRegistry $fields,
        private TemplateRegistry $templates,
        private SchemaExporter $exporter,
        private SchemaReader $reader,
        private RoleSync $roles,
    ) {}

    /**
     * Write one YAML file per language, field and template. Files of items that no longer exist
     * are removed, so the directory mirrors the database.
     *
     * @return int number of files written
     */
    public function export(string $directory): int
    {
        return $this->exporter->export($directory);
    }

    /**
     * Apply the files in a directory. Destructive changes (deleting languages, fields or templates
     * that are not in the files) only run with $force. With $dryRun everything is rolled back.
     */
    public function apply(string $directory, bool $force = false, bool $dryRun = false): SchemaSyncResult
    {
        $result = new SchemaSyncResult;
        $files = $this->reader->read($directory);

        DB::beginTransaction();

        try {
            $this->applyLanguages($files['languages'], $result);
            $this->applyFields($files['fields'], $result);
            $this->applyTemplates($files['templates'], $result);
            $roles = $this->roles->read($directory);
            $this->runAll($result, $this->roles->saves($roles, $result));
            $this->deleteMissing($files, $force, $result);
            $this->runAll($result, $this->roles->deletes($roles, $force, $result));

            $dryRun || $result->hasErrors() ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        } finally {
            $this->runner->flushCaches();
        }

        $result->applied = ! $dryRun && ! $result->hasErrors();

        return $result;
    }

    /**
     * @param  list<LanguageItem>  $items
     */
    private function applyLanguages(array $items, SchemaSyncResult $result): void
    {
        // The default language first (on an empty site the first language created becomes the
        // default), then languages without a fallback; fallbacks are set in a second pass.
        $rank = fn (array $l): int => $l['default'] ? 0 : ($l['fallback'] === null ? 1 : 2);
        usort($items, fn (array $a, array $b): int => $rank($a) <=> $rank($b));

        foreach ($items as $item) {
            $this->applyLanguage($item, $result);
        }

        $this->applyFallbacks($items, $result);
    }

    /**
     * @param  LanguageItem  $item
     */
    private function applyLanguage(array $item, SchemaSyncResult $result): void
    {
        $existing = $this->languages->find($item['uuid']) ?? $this->languages->find($item['code']);

        if ($existing === null) {
            $this->run($result, CreateLanguage::class, array_intersect_key($item, array_flip(['uuid', 'code', 'name', 'label', 'locale', 'prefix', 'active', 'provider_codes'])));

            return;
        }

        $changes = $this->languageChanges($existing, $item);

        if ($changes !== []) {
            $this->run($result, UpdateLanguage::class, ['language' => $existing->code] + $changes);
        }

        if ($item['default'] && $existing->id !== $this->languages->default()->id) {
            $result->errors[] = "Language {$item['code']} is the default in the files; switching the default language is not supported yet.";
        }
    }

    /**
     * What differs between the language and its file, as languages.update takes it.
     *
     * @param  LanguageItem  $item
     * @return array<string, mixed>
     */
    private function languageChanges(Language $existing, array $item): array
    {
        $changes = array_filter([
            'code' => $existing->code !== $item['code'] ? $item['code'] : null,
            'name' => $existing->name !== $item['name'] ? $item['name'] : null,
            'label' => $existing->label !== $item['label'] ? ($item['label'] ?? '') : null,
            'locale' => $existing->locale !== $item['locale'] ? ($item['locale'] ?? '') : null,
            'prefix' => $existing->url_prefix !== $item['prefix'] ? $item['prefix'] : null,
            'active' => $existing->is_active !== $item['active'] ? $item['active'] : null,
        ], fn (mixed $v): bool => $v !== null);

        if (($changes['locale'] ?? null) === '') {
            $changes['locale'] = null;
        }

        return $changes;
    }

    /**
     * Fallbacks in a second pass, once every language exists.
     *
     * @param  list<LanguageItem>  $items
     */
    private function applyFallbacks(array $items, SchemaSyncResult $result): void
    {
        foreach ($items as $item) {
            $language = $this->languages->find($item['uuid']);
            $current = $language?->fallback_id === null ? null : $this->languages->find($language->fallback_id)?->code;

            if ($language !== null && $current !== $item['fallback'] && ! $item['default']) {
                $this->run($result, UpdateLanguage::class, ['language' => $language->code, 'fallback' => $item['fallback']]);
            }
        }
    }

    /**
     * @param  list<FieldItem>  $items
     */
    private function applyFields(array $items, SchemaSyncResult $result): void
    {
        // Repeaters and matrices name their subfields, which must exist first; files come by name.
        usort($items, fn (array $a, array $b): int => $this->holdsFields($a) <=> $this->holdsFields($b));

        foreach ($items as $item) {
            $existing = $this->fields->find($item['uuid']) ?? $this->fields->find($item['name']);

            if ($existing === null) {
                $this->run($result, CreateField::class, [
                    'uuid' => $item['uuid'],
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'label' => $item['label'],
                    'translatable' => $item['translatable'],
                    'settings' => $item['settings'],
                ]);

                continue;
            }

            if ($existing->name !== $item['name'] || $existing->type !== $item['type'] || $existing->isTranslatable() !== $item['translatable']) {
                $result->errors[] = "Field {$existing->name}: renaming, changing type or changing translatable is not supported by schema sync yet.";

                continue;
            }

            if ($existing->labels()->toStorage() !== $item['label'] || $existing->config() != $item['settings']) {
                $this->run($result, UpdateField::class, ['field' => $existing->name, 'label' => $item['label'], 'settings' => $item['settings']]);
            }
        }
    }

    /**
     * @param  FieldItem  $field
     */
    private function holdsFields(array $field): bool
    {
        return isset($field['settings']['fields']) || isset($field['settings']['types']);
    }

    /**
     * @param  list<TemplateItem>  $items
     */
    private function applyTemplates(array $items, SchemaSyncResult $result): void
    {
        foreach ($items as $item) {
            $existing = $this->templates->find($item['uuid']) ?? $this->templates->find($item['name']);
            $settings = $item['settings'];

            if ($existing === null) {
                $this->run($result, CreateTemplate::class, [
                    'uuid' => $item['uuid'],
                    'name' => $item['name'],
                    'label' => $item['label'],
                    'fields' => $item['fields'],
                ] + array_intersect_key($settings, array_flip(Template::SETTING_KEYS)));

                continue;
            }

            $current = $this->exporter->templateData($existing);

            if ($current['label'] !== $item['label'] || $current['fields'] != $item['fields'] || $current['settings'] != $settings) {
                $this->run($result, UpdateTemplate::class, [
                    'template' => $existing->name,
                    'label' => $item['label'],
                    'fields' => $item['fields'],
                ] + array_intersect_key($settings, array_flip(Template::SETTING_KEYS)));
            }
        }
    }

    /**
     * @param  SchemaFiles  $files
     */
    private function deleteMissing(array $files, bool $force, SchemaSyncResult $result): void
    {
        $uuids = fn (string $kind): array => array_column($files[$kind], 'uuid');
        $deletions = [
            [DeleteTemplate::class, 'template', $this->templates->all()->reject(fn (Template $t): bool => in_array($t->uuid, $uuids('templates'), true))->map(fn (Template $t): string => $t->name)],
            [DeleteField::class, 'field', $this->fields->all()->reject(fn (Field $f): bool => in_array($f->uuid, $uuids('fields'), true))->map(fn (Field $f): string => $f->name)],
            [DeleteLanguage::class, 'language', $this->languages->all()->reject(fn (Language $l): bool => in_array($l->uuid, $uuids('languages'), true))->map(fn (Language $l): string => $l->code)],
        ];

        foreach ($deletions as [$action, $key, $names]) {
            foreach ($names as $name) {
                if (! $force) {
                    $result->skipped[] = "Delete {$key} {$name} (not in the files; run with --force to delete)";

                    continue;
                }

                $this->run($result, $action, [$key => $name]);
            }
        }
    }

    /**
     * @param  list<array{0: class-string<Action>, 1: array<string, mixed>}>  $runs
     */
    private function runAll(SchemaSyncResult $result, array $runs): void
    {
        foreach ($runs as [$action, $input]) {
            $this->run($result, $action, $input);
        }
    }

    /**
     * @param  class-string<Action>  $action
     * @param  array<string, mixed>  $input
     */
    private function run(SchemaSyncResult $result, string $action, array $input): void
    {
        try {
            $run = $this->runner->run($action, $input);
            array_push($result->changes, ...$run->changes->summary());
        } catch (Throwable $e) {
            $result->errors[] = $action::name().': '.$e->getMessage();
        }
    }
}
