<?php

declare(strict_types=1);

namespace LynxCms\Core\Doctor;

/**
 * How bad a doctor finding is: an error breaks something visitors or editors rely on and makes
 * lynx:doctor fail; a warning is worth a look but the site works.
 *
 * @api
 */
enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
