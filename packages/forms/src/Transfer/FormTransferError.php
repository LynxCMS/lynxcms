<?php

declare(strict_types=1);

namespace LynxCms\Forms\Transfer;

use RuntimeException;

/**
 * A form that can't be exported (ADR-0146): one the site lacks, or one defined in code, which
 * travels with the app's code instead.
 *
 * @api
 */
final class FormTransferError extends RuntimeException {}
