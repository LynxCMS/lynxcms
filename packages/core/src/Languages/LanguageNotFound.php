<?php

declare(strict_types=1);

namespace LynxCms\Core\Languages;

use RuntimeException;

/**
 * Thrown when no language has the code, id or prefix asked for.
 *
 * @api
 */
final class LanguageNotFound extends RuntimeException
{
    public static function for(mixed $reference): self
    {
        return new self('Language ['.(is_scalar($reference) ? $reference : get_debug_type($reference)).'] does not exist.');
    }
}
