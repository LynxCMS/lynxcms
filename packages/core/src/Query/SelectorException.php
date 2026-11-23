<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use InvalidArgumentException;

/**
 * Thrown for a selector Lynx cannot read; its message says what is wrong.
 *
 * @api
 */
final class SelectorException extends InvalidArgumentException {}
