<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use InvalidArgumentException;
use JsonSerializable;
use LynxCms\Core\Fields\PresentsValue;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Concerns\DefersFields;
use LynxCms\Core\Pages\Concerns\HasNamesAndUrls;
use LynxCms\Core\Pages\Concerns\SerializesPage;
use LynxCms\Core\Pages\Concerns\TracksChanges;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * A page: a domain object hydrated by PageLoader, not an Eloquent model (ADR-0010).
 *
 *   $page->title                       // TranslatableValue, current language with fallback
 *   $page->title = 'New title';        // sets the current language
 *   $page->setLanguageValue('pt', 'title', 'Novo título');
 *   $page->setLanguageName('pt', 'novo-titulo');
 *   $page->save();
 *
 * @property-read int|null $id
 * @property string $name
 * @property-read string $url
 * @property-read string|null $path
 * @property-read Template $template
 * @property-read Page|null $parent
 * @property-read PageArray $children
 *
 * @api
 */
class Page implements JsonSerializable
{
    use DefersFields;
    use HasNamesAndUrls;
    use SerializesPage;
    use TracksChanges;

    public ?int $id = null;

    public int $status = 0;

    public int $sort = 0;

    public ?string $createdAt = null;

    public ?string $updatedAt = null;

    /** Raised by every write to the page row; pages.save refuses a stale "expected_revision". */
    public int $revision = 0;

    public ?string $publishedAt = null;

    public ?string $createdBy = null;

    public ?string $modifiedBy = null;

    /**
     * Stored values: field name => [language id (0 for shared fields) => value].
     *
     * @var array<string, array<int, mixed>>
     */
    private array $values = [];

    /** @var array<string, array<int, true>> */
    private array $dirtyValues = [];

    /**
     * Names per language: language id => ['name' => string, 'status' => 'published'|'disabled'].
     *
     * @var array<int, array{name: string, status: string}>
     */
    private array $names = [];

    /** @var array<int, true> */
    private array $dirtyNames = [];

    private bool $dirtyAttributes = false;

    public function __construct(public int $templateId, public ?int $parentId = null) {}

    // ----- Built-in properties and field access -------------------------------------------------

    public function __get(string $key): mixed
    {
        return match ($key) {
            'id' => $this->id,
            'name' => $this->name(),
            'url' => $this->url(),
            'path' => $this->path(),
            'template' => $this->template(),
            'parent' => $this->parent(),
            'children' => $this->children(),
            'status' => $this->status,
            default => $this->get($key),
        };
    }

    public function __set(string $key, mixed $value): void
    {
        if ($key === 'name') {
            $this->setLanguageName($this->context()->current(), Value::string($value));

            return;
        }

        $this->setLanguageValue($this->context()->current(), $key, $value);
    }

    public function __isset(string $key): bool
    {
        return in_array($key, ['id', 'name', 'url', 'path', 'template', 'parent', 'children', 'status'], true)
            || $this->templateField($key) !== null;
    }

    /**
     * A field value: TranslatableValue for translatable fields; for shared ones, what the field type
     * presents (a PageArray for page references, ADR-0042), else the stored value. A text with no
     * value in the current language's fallback chain reads as null (ADR-0070, ADR-0087); read
     * another language with getLanguageValue().
     */
    public function get(string $field): mixed
    {
        $templateField = $this->templateField($field);

        if ($templateField === null) {
            app(UnknownFields::class)->report($this, $field);

            return null;
        }

        $definition = $templateField->field;
        $this->loadDeferred($field);

        if ($definition->isTranslatable()) {
            $translatable = new TranslatableValue($definition, $this->values[$field] ?? [], $this->context(), $this->registry());

            return $translatable->isEmpty() ? null : $translatable;
        }

        $value = $this->values[$field][0] ?? null;
        $type = $definition->fieldType();

        return $type instanceof PresentsValue ? $type->present($value, $definition) : $value;
    }

    /** Strict value in one language, no fallback. Shared fields ignore the language. */
    public function getLanguageValue(Language|string $language, string $field): mixed
    {
        $definition = $this->requireField($field)->field;
        $key = $definition->isTranslatable() ? $this->registry()->get($language)->id : 0;
        $this->loadDeferred($field);

        return $this->values[$field][$key] ?? null;
    }

    /**
     * The stored value, unformatted and unescaped, as ProcessWire's getUnformatted() (ADR-0031): in
     * the current language with fallback, or strictly in $language. Page references stay ids.
     */
    public function getUnformatted(string $field, Language|string|null $language = null): mixed
    {
        if ($language !== null) {
            return $this->getLanguageValue($language, $field);
        }

        $templateField = $this->templateField($field);

        if ($templateField === null) {
            app(UnknownFields::class)->report($this, $field);
        }

        if ($templateField === null || ! $templateField->field->isTranslatable()) {
            return $templateField === null ? null : ($this->rawValuesOf($field)[0] ?? null);
        }

        $value = $this->get($field);

        return $value instanceof FieldValue ? $value->value() : $value;
    }

    /**
     * Set a value in one language. null removes the value so it falls back; '' stores an
     * explicit empty value. Shared fields ignore the language.
     */
    public function setLanguageValue(Language|string $language, string $field, mixed $value): static
    {
        $definition = $this->requireField($field)->field;
        $key = $definition->isTranslatable() ? $this->registry()->get($language)->id : 0;

        if ($value instanceof TranslatableValue) {
            $value = $value->value();
        }

        // Loaded first, so a later read of the field cannot replace what is set here.
        $this->loadDeferred($field);

        if ($value === null) {
            unset($this->values[$field][$key]);
        } else {
            $this->values[$field][$key] = $value;
        }

        $this->dirtyValues[$field][$key] = true;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $values  field => value, in one language
     */
    public function fill(Language|string $language, array $values): static
    {
        foreach ($values as $field => $value) {
            $this->setLanguageValue($language, $field, $value);
        }

        return $this;
    }

    // ----- Relations -----------------------------------------------------------------------------

    public function template(): Template
    {
        return app(TemplateRegistry::class)->get($this->templateId);
    }

    public function parent(): ?Page
    {
        return $this->parentId === null ? null : app(PageLoader::class)->find($this->parentId);
    }

    public function children(?string $selector = null): PageArray
    {
        if ($this->id === null) {
            return new PageArray([]);
        }

        return lynx()->pages->find(trim("parent={$this->id}, ".($selector ?? ''), ', '));
    }

    public function save(bool $dryRun = false): mixed
    {
        return lynx()->pages->save($this, dryRun: $dryRun);
    }

    public function templateField(string $field): ?TemplateField
    {
        return app(TemplateRegistry::class)->fields($this->templateId)->get($field);
    }

    private function requireField(string $field): TemplateField
    {
        return $this->templateField($field)
            ?? throw new InvalidArgumentException("Template [{$this->template()->name}] has no field [{$field}].");
    }

    private function context(): LanguageContext
    {
        return app(LanguageContext::class);
    }

    private function registry(): LanguageRegistry
    {
        return app(LanguageRegistry::class);
    }
}
