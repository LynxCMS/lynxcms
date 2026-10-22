<?php

declare(strict_types=1);

namespace LynxCms\Contracts\Actions;

use RuntimeException;

/**
 * Thrown by a before-hook to stop an action. Nothing is written.
 *
 * @api
 */
class ActionHalted extends RuntimeException {}
