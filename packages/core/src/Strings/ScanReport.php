<?php

declare(strict_types=1);

namespace LynxCms\Core\Strings;

/**
 * What scanning one Blade file found (ADR-0047).
 *
 * @internal
 */
final class ScanReport
{
    /** @var list<StringDefinition> */
    public array $definitions = [];

    /** @var list<string> */
    public array $errors = [];

    /** @var list<string> view names the file pulls in */
    public array $views = [];
}
