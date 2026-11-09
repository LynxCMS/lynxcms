<?php

declare(strict_types=1);

namespace LynxCms\Core\Doctor;

/**
 * One thing lynx:doctor looks at (ADR-0109). A module adds its own with DoctorChecks::add().
 * A check only reads: it never changes the site.
 *
 * @api
 */
interface Check
{
    /** A short stable key, e.g. "template-views", for --json and scripts. */
    public function key(): string;

    /** What is checked, as the report's line when all is well, e.g. "Pages have their template's view". */
    public function label(): string;

    /**
     * What is wrong; empty when all is well.
     *
     * @return list<Finding>
     */
    public function run(): array;
}
