<?php

declare(strict_types=1);

namespace LynxCms\Core\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use Override;

/**
 * A page template: an ordered list of fields plus flags (has_url, singleton, allowed_templates) and
 * family rules (allow_children, child_templates, parent_templates, ADR-0032).
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $label
 * @property array<string, mixed>|null $settings
 *
 * @api
 */
final class Template extends Model
{
    /** Settings the template actions accept besides label and fields; schema sync carries them. */
    public const array SETTING_KEYS = ['has_url', 'singleton', 'allowed_templates', 'allow_children', 'child_templates', 'parent_templates', 'tabs'];

    #[Override]
    protected $table = Tables::TEMPLATES;

    #[Override]
    protected $guarded = [];

    protected function casts(): array
    {
        return ['settings' => 'array'];
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

    /**
     * @return BelongsToMany<Field, $this>
     */
    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(Field::class, Tables::TEMPLATE_FIELDS)
            ->withPivot(['sort', 'settings'])
            ->orderByPivot('sort');
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->settings ?? [];
    }

    /** Pages on templates without URLs (settings, reusable content) are never routed (ADR-0008). */
    public function hasUrl(): bool
    {
        return (bool) ($this->config()['has_url'] ?? true);
    }

    public function isSingleton(): bool
    {
        return (bool) ($this->config()['singleton'] ?? false);
    }

    /**
     * Templates a page on this template may switch to. Empty means any.
     *
     * @return list<string>
     */
    public function allowedTemplates(): array
    {
        return Value::strings($this->config()['allowed_templates'] ?? []);
    }

    /** Whether pages using this template may have children (ADR-0032). */
    public function allowsChildren(): bool
    {
        return (bool) ($this->config()['allow_children'] ?? true);
    }

    /**
     * Templates children of these pages may use. Empty means any.
     *
     * @return list<string>
     */
    public function childTemplates(): array
    {
        return Value::strings($this->config()['child_templates'] ?? []);
    }

    /**
     * Templates the parent of these pages may use. Empty means any.
     *
     * @return list<string>
     */
    public function parentTemplates(): array
    {
        return Value::strings($this->config()['parent_templates'] ?? []);
    }
}
