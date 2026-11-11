<?php

declare(strict_types=1);

namespace LynxCms\Tests\Fixtures\AppAuth;

use Illuminate\Foundation\Auth\User;
use Override;

/** An app's user model kept in a table not named users. */
final class Member extends User
{
    #[Override]
    protected $table = 'members';
}
