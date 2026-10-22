<?php

declare(strict_types=1);

namespace LynxCms\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use LynxCms\Forms\FormsTables;
use Override;

/**
 * A file sent with a submission, on the private forms disk.
 *
 * @property int $id
 * @property int $submission_id
 * @property string $field
 * @property string $path
 * @property string $name
 * @property string $mime
 * @property int $bytes
 *
 * @api
 */
final class SubmissionFile extends Model
{
    #[Override]
    protected $table = FormsTables::FILES;

    #[Override]
    protected $guarded = [];
}
