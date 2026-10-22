<?php

declare(strict_types=1);

namespace LynxCms\Core\Activity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LynxCms\Core\Support\Tables;
use Override;

/**
 * @property int $id
 * @property string $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property int|null $language_id
 * @property string|null $actor_id
 * @property string $actor_kind
 * @property array<int, array<string, mixed>> $changes
 * @property array<string, mixed>|null $meta
 * @property Carbon $created_at
 *
 * @api
 */
final class ActivityEvent extends Model
{
    public const UPDATED_AT = null;

    #[Override]
    protected $table = Tables::ACTIVITY;

    #[Override]
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
