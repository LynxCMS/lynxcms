<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use Closure;

/**
 * A field type whose values name stored files, such as images (ADR-0050). Content packages copy
 * the files with the page and, on import, store them again for the new page and rewrite the
 * value through mapFiles().
 *
 * @api
 */
interface CarriesFiles
{
    /**
     * The stored files the value names, e.g. "42/still.jpg".
     *
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    public function files(mixed $value, array $settings): array;

    /**
     * The value with each file replaced by $map's result.
     *
     * @param  Closure(string): string  $map
     * @param  array<string, mixed>  $settings
     */
    public function mapFiles(mixed $value, Closure $map, array $settings): mixed;
}
