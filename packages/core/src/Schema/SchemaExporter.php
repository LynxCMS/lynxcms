<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema;

use Illuminate\Filesystem\Filesystem;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\Layout\FieldListOutput;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Core\Templates\TemplateRegistry;
use Symfony\Component\Yaml\Yaml;

/**
 * The export side of schema sync (ADR-0014): one YAML file per language, field and template, and
 * the data each file holds, which apply also compares against.
 *
 * @phpstan-import-type LanguageItem from SchemaSync
 * @phpstan-import-type FieldItem from SchemaSync
 * @phpstan-import-type TemplateItem from SchemaSync
 *
 * @internal
 */
final readonly class SchemaExporter
{
    public function __construct(
        private Filesystem $files,
        private LanguageRegistry $languages,
        private FieldRegistry $fields,
        private TemplateRegistry $templates,
        private RoleSync $roles,
    ) {}

    /** @return int number of files written */
    public function export(string $directory): int
    {
        $written = 0;

        // kind => file name (without extension) => data
        $sets = [
            'languages' => $this->languages->all()->mapWithKeys(fn (Language $l): array => [$l->code => $this->languageData($l)])->all(),
            'fields' => $this->fields->all()->mapWithKeys(fn (Field $f): array => [$f->name => $this->fieldData($f)])->all(),
            'templates' => $this->templates->all()->mapWithKeys(fn (Template $t): array => [$t->name => $this->templateData($t)])->all(),
            'roles' => $this->roles->export(),
        ];

        foreach ($sets as $kind => $items) {
            $dir = rtrim($directory, '/')."/{$kind}";
            $this->files->ensureDirectoryExists($dir);
            $keep = [];

            foreach ($items as $name => $item) {
                $file = "{$dir}/{$name}.yaml";
                $this->files->put($file, Yaml::dump($item, 4, 2));
                $keep[] = $file;
                $written++;
            }

            foreach (Value::strings($this->files->glob("{$dir}/*.yaml")) as $file) {
                if (! in_array($file, $keep, true)) {
                    $this->files->delete($file);
                }
            }
        }

        return $written;
    }

    /**
     * @return LanguageItem
     */
    private function languageData(Language $language): array
    {
        return [
            'uuid' => $language->uuid,
            'code' => $language->code,
            'name' => $language->name,
            'label' => $language->label,
            'locale' => $language->locale,
            'fallback' => $language->fallback_id === null ? null : $this->languages->find($language->fallback_id)?->code,
            'prefix' => $language->url_prefix,
            'active' => $language->is_active,
            'default' => $language->id === $this->languages->default()->id,
            'provider_codes' => $language->provider_codes,
        ];
    }

    /**
     * @return FieldItem
     */
    private function fieldData(Field $field): array
    {
        return [
            'uuid' => $field->uuid,
            'name' => $field->name,
            'type' => $field->type,
            'label' => $field->labels()->toStorage(),
            'translatable' => $field->isTranslatable(),
            'settings' => $field->config(),
        ];
    }

    /**
     * @return TemplateItem
     */
    public function templateData(Template $template): array
    {
        return [
            'uuid' => $template->uuid,
            'name' => $template->name,
            'label' => $template->labels()->toStorage(),
            'settings' => array_diff_key($template->config(), ['sections' => true]),
            'fields' => $this->fieldList($template),
        ];
    }

    /**
     * @return list<string|array<string, mixed>>
     */
    private function fieldList(Template $template): array
    {
        $fields = array_values($this->templates->fields($template)->map(
            fn (TemplateField $f): array => ['name' => $f->name(), 'settings' => $f->settings],
        )->all());

        return FieldListOutput::nest($fields, Value::list($template->config()['sections'] ?? []));
    }
}
