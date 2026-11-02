<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\Concerns;

use LynxCms\Core\Models\Field;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Pages\TranslatableValue;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * A page as data for APIs, agents and exports. Part of Page, kept apart to keep that class small.
 *
 * @internal
 */
trait SerializesPage
{
    /**
     * Field values in the current language, for APIs and agents.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'template' => $this->template()->name,
            'parent_id' => $this->parentId,
            'name' => $this->name(),
            'url' => $this->id === null ? null : $this->url(),
            'status' => PageStatus::toNames($this->status),
            'revision' => $this->revision,
            'languages' => [],
            'fields' => [],
        ];

        foreach ($this->names as $languageId => $name) {
            $code = $this->registry()->find($languageId)?->code;

            if ($code !== null) {
                $data['languages'][$code] = $name;
            }
        }

        foreach (app(TemplateRegistry::class)->fields($this->templateId) as $name => $templateField) {
            $value = $this->get($name);
            $data['fields'][$name] = match (true) {
                $value instanceof TranslatableValue => [
                    'value' => $value->value(),
                    'language' => $value->resolvedLanguage()?->code,
                    'fallback' => $value->isFallback(),
                ],
                $value instanceof PageArray => $value->ids(),
                default => $value,
            };
        }

        return $data;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * What var_dump(), print_r(), tinker and Tinkerwell show (the last two through PageCaster):
     * the page's own data, the values loaded so far by language code (* for shared fields) and
     * the fields not loaded yet. Without it they follow the page into its loader and print every
     * template and field of the site. Looking never loads a field.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'id' => $this->id,
            'template' => app(TemplateRegistry::class)->find($this->templateId)?->name,
            'parentId' => $this->parentId,
            'status' => PageStatus::toNames($this->status),
            'sort' => $this->sort,
            'revision' => $this->revision,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'publishedAt' => $this->publishedAt,
            'names' => $this->byLanguageCode($this->names),
            'values' => array_map($this->byLanguageCode(...), $this->values),
            'notLoaded' => array_keys($this->deferredFields),
            'unsaved' => array_keys($this->dirtyValues),
        ];
    }

    /**
     * @param  array<int, mixed>  $byLanguageId
     * @return array<string, mixed>
     */
    private function byLanguageCode(array $byLanguageId): array
    {
        $byCode = [];

        foreach ($byLanguageId as $languageId => $value) {
            $byCode[$languageId === 0 ? '*' : ($this->registry()->find($languageId)->code ?? (string) $languageId)] = $value;
        }

        return $byCode;
    }

    /**
     * Translatable values keyed by language code, for agents and exports.
     *
     * @return array<string, array<string, mixed>>
     */
    public function valuesByLanguage(): array
    {
        $result = [];
        $this->loadAllDeferred();

        foreach (app(TemplateRegistry::class)->fields($this->templateId) as $name => $templateField) {
            /** @var Field $field */
            $field = $templateField->field;

            foreach ($this->values[$name] ?? [] as $languageId => $value) {
                $code = $field->isTranslatable() ? ($this->registry()->find($languageId)->code ?? (string) $languageId) : '*';
                $result[$code][$name] = $value;
            }
        }

        return $result;
    }
}
