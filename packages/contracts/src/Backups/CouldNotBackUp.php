<?php

declare(strict_types=1);

namespace LynxCms\Contracts\Backups;

use RuntimeException;

/**
 * A database backup could not be made: the driver is not supported, or the dump or a disk failed.
 *
 * @api
 */
final class CouldNotBackUp extends RuntimeException {}
