<?php

declare(strict_types=1);

namespace LynxCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use LynxCms\Core\Support\Tables;
use Override;

/**
 * @property string $key
 * @property string|null $value
 *
 * @internal
 */
final class Setting extends Model
{
    #[Override]
    public $incrementing = false;

    #[Override]
    public $timestamps = false;

    #[Override]
    protected $table = Tables::SETTINGS;

    #[Override]
    protected $primaryKey = 'key';

    #[Override]
    protected $keyType = 'string';

    #[Override]
    protected $guarded = [];

    public static function read(string $key, ?string $default = null): ?string
    {
        return self::query()->find($key)->value ?? $default;
    }

    public static function write(string $key, ?string $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
