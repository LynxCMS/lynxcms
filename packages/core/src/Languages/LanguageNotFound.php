<?php

declare(strict_types=1);

namespace LynxCms\Core\Languages;

use RuntimeException;

/** @api */
final class LanguageNotFound extends RuntimeException
{
    public static function for(mixed $reference): self
    {
        return new self('Language ['.(is_scalar($reference) ? $reference : get_debug_type($reference)).'] does not exist.');
    }
}
