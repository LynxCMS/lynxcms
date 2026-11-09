<?php

declare(strict_types=1);

namespace LynxCms\Core\Doctor\Checks;

use LynxCms\Core\Doctor\Check;
use LynxCms\Core\Doctor\Finding;
use LynxCms\Core\Doctor\Severity;
use LynxCms\Core\Fields\FieldUsage;

/**
 * Fields no template uses, directly or inside a repeater's or matrix's items: left over from a
 * removed template or added and never placed.
 *
 * @internal
 */
final readonly class UnusedFields implements Check
{
    public function __construct(private FieldUsage $usage) {}

    public function key(): string
    {
        return 'unused-fields';
    }

    public function label(): string
    {
        return 'Every field is on a template';
    }

    public function run(): array
    {
        $unused = $this->usage->unused();

        return $unused === [] ? [] : [new Finding(
            Severity::Warning,
            'No template uses '.implode(', ', array_map(fn (string $name): string => '['.$name.']', $unused)).'.',
            'Add them to a template, or delete them with fields.delete (their values go too).',
        )];
    }
}
