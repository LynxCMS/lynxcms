<?php

declare(strict_types=1);

namespace LynxCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use Override;

/**
 * A content language. Languages are rows, so adding one never needs a migration (ADR-0003).
 *
 * @property int $id
 * @property string $uuid
 * @property string $code
 * @property string $name
 * @property string|null $label short label for the admin's language tabs
 * @property string|null $locale
 * @property int|null $fallback_id
 * @property string $url_prefix
 * @property array<string, string>|null $provider_codes
 * @property bool $is_active
 * @property int $sort
 *
 * @api
 */
final class Language extends Model
{
    #[Override]
    protected $table = Tables::LANGUAGES;

    #[Override]
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'provider_codes' => 'array',
            'is_active' => 'bool',
            'sort' => 'int',
            'fallback_id' => 'int',
        ];
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function fallback(): BelongsTo
    {
        return $this->belongsTo(self::class, 'fallback_id');
    }

    /** The locale for Laravel and HTML: the explicit locale, else the code (except "default"). */
    public function appLocale(): string
    {
        return $this->locale ?? ($this->code === 'default' ? Value::string(config('lynx.base_locale', 'en'), 'en') : $this->code);
    }

    /** What the admin's language tabs show: the label, else the name. */
    public function tabLabel(): string
    {
        return $this->label ?? $this->name;
    }

    public function isDefault(): bool
    {
        return lynx()->languages->default()->id === $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'label' => $this->label,
            'locale' => $this->locale,
            'fallback' => $this->fallback_id ? lynx()->languages->get($this->fallback_id)->code : null,
            'prefix' => $this->url_prefix,
            'active' => $this->is_active,
            'default' => $this->isDefault(),
        ];
    }
}
