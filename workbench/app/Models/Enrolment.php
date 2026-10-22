<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

final class Enrolment extends Model
{
    #[Override]
    protected $guarded = [];
}
