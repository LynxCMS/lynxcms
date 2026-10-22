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
