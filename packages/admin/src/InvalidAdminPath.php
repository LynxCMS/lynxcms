<?php

declare(strict_types=1);

namespace LynxCms\Admin;

use InvalidArgumentException;

/**
 * LYNX_ADMIN_PREFIX (or a path given to lynx:admin-path) is empty or not a URL path.
 *
 * @internal
 */
final class InvalidAdminPath extends InvalidArgumentException {}
