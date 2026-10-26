<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\Items;

use LynxCms\Core\Fields\Items\Subfield;
use LynxCms\Core\Fields\PresentsValue;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\TranslatableValue;
use LynxCms\Core\Support\Value;
use LynxCms\Core\View\MatrixRenderer;

/**
 * One item of a repeater or matrix as templates read it (ADR-0044): $item->role is the value in
 * the current language with fallback, $item->partner the pages it references, like page fields.
 *
 * @property-read string $key
 *
 * @api
 */
final readonly class Item
{
    /**
     * @param  array{key: string, type: string|null, enabled: bool, depth: int, values: array<string, mixed>}  $item
     * @param  array<string, Subfield>  $subfields  name => subfield, the fields this item shows
     */
    public function __construct(private array $item, private array $subfields, private string $field = '') {}

    /** The item field holding this item. */
    public function field(): string
    {
        return $this->field;
    }

    /** The item as its type's view renders it (MatrixRenderer); $page picks the template's own views first. */
    public function render(?Page $page = null): string
    {
        return app(MatrixRenderer::class)->item($this, $page);
    }

    public function key(): string
    {
        return $this->item['key'];
    }

    /** The matrix type's slug; null for repeater items. */
    public function type(): ?string
    {
        return $this->item['type'];
    }

    public function depth(): int
    {
        return $this->item['depth'];
    }

    public function isEnabled(): bool
    {
        return $this->item['enabled'];
    }

    public function get(string $name): mixed
    {
        $subfield = $this->subfields[$name] ?? null;
        $value = $this->item['values'][$name] ?? null;

        if ($subfield === null) {
            return null;
        }

        if ($subfield->isTranslatable()) {
            $languages = app(LanguageRegistry::class);
            $byId = [];

            foreach (Value::map($value) as $code => $languageValue) {
                $language = $languages->find($code);

                if ($language !== null) {
                    $byId[(int) $language->id] = $languageValue;
                }
            }

            $translatable = new TranslatableValue($subfield->field, $byId, app(LanguageContext::class), $languages);

            return $translatable->isEmpty() ? null : $translatable;
        }

        $type = $subfield->type();

        return $type instanceof PresentsValue ? $type->present($value, $subfield->field) : $value;
    }

    /** The stored value, without fallback or presentation. */
    public function getUnformatted(string $name): mixed
    {
        return $this->item['values'][$name] ?? null;
    }

    public function __get(string $name): mixed
    {
        return $name === 'key' ? $this->key() : $this->get($name);
    }

    public function __isset(string $name): bool
    {
        return $name === 'key' || isset($this->subfields[$name]);
    }
}
