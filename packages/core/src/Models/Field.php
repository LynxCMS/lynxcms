<?php

declare(strict_types=1);

namespace LynxCms\Core\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use LynxCms\Contracts\Fields\FieldType;
use LynxCms\Core\Fields\FieldTypeRegistry;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use Override;

/**
 * A field definition. Its values live in their own table, lynx_f_{name} (ADR-0004).
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $type
 * @property string $label
 * @property string $scope
 * @property array<string, mixed>|null $settings
 * @property int $schema_version
 *
 * @api
 */
final class Field extends Model
{
    public const string SHARED = 'shared';

    public const string TRANSLATABLE = 'translatable';

    /** The field every page shows in lists and links; always loaded with the page. */
    public const string TITLE = 'title';

    #[Override]
    protected $table = Tables::FIELDS;

    #[Override]
    protected $guarded = [];

    protected function casts(): array
    {
        return ['settings' => 'array', 'schema_version' => 'int'];
    }

    /**
     * The label in the current language (ADR-0043); labels() gives every language. Assigning takes
     * a string or a map of language codes to text.
     *
     * @return Attribute<string, string|array<string, string>|LocalizedText>
     */
    protected function label(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): string => LocalizedText::from($value)->in(),
            set: fn (mixed $value): string => LocalizedText::from($value)->toColumn(),
        );
    }

    public function labels(): LocalizedText
    {
        return LocalizedText::from($this->getAttributes()['label'] ?? '');
    }

    /** The description under the field's label, in the current language. */
    public function description(): string
    {
        return LocalizedText::from($this->config()['description'] ?? '')->in();
    }

    /** A short hint next to the field's input, in the current language. */
    public function notes(): string
    {
        return LocalizedText::from($this->config()['notes'] ?? '')->in();
    }

    public function fieldType(): FieldType
    {
        return app(FieldTypeRegistry::class)->get($this->type);
    }

    public function isTranslatable(): bool
    {
        return $this->scope === self::TRANSLATABLE;
    }

    public function table(): string
    {
        return Tables::field($this->name);
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->settings ?? [];
    }

    /**
     * Loaded with every page instead of on first read (ADR-0067): the title, which nearly every
     * listing shows, and fields whose "autoload" setting is on, like ProcessWire's autojoin.
     */
    public function loadsWithPage(): bool
    {
        return $this->name === self::TITLE || ($this->config()['autoload'] ?? false) === true;
    }

    /** "inherit" (default) or "none": whether missing values fall back through the language chain. */
    public function fallbackMode(): string
    {
        return Value::string($this->config()['fallback'] ?? 'inherit', 'inherit');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'label' => $this->labels()->toStorage(),
            'translatable' => $this->isTranslatable(),
            'settings' => $this->config(),
        ];
    }
}
