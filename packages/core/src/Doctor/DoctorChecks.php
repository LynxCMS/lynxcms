<?php

declare(strict_types=1);

namespace LynxCms\Core\Doctor;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use LynxCms\Core\Doctor\Checks\OrphanedValues;
use LynxCms\Core\Doctor\Checks\Scheduler;
use LynxCms\Core\Doctor\Checks\TemplateViews;
use LynxCms\Core\Doctor\Checks\UnusedFields;

/**
 * The checks lynx:doctor runs (ADR-0109): Lynx's own, then those modules add at boot.
 *
 * @api
 */
final class DoctorChecks
{
    /** @var list<class-string<Check>> */
    private const array CORE = [TemplateViews::class, UnusedFields::class, OrphanedValues::class, Scheduler::class];

    /** @var list<class-string<Check>|Check> */
    private array $added = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<Check>|Check  ...$checks
     */
    public function add(string|Check ...$checks): void
    {
        array_push($this->added, ...$checks);
    }

    /**
     * @return list<Check>
     */
    public function all(): array
    {
        return array_map(fn (string|Check $check): Check => $check instanceof Check ? $check : $this->resolve($check), [...self::CORE, ...$this->added]);
    }

    /**
     * @param  class-string<Check>  $class
     */
    private function resolve(string $class): Check
    {
        $check = $this->container->make($class);

        return $check instanceof Check ? $check : throw new InvalidArgumentException("[{$class}] is not a doctor check.");
    }
}
