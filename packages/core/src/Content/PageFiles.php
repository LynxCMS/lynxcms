<?php

declare(strict_types=1);

namespace LynxCms\Core\Content;

/**
 * Where the files of CarriesFiles fields live, for content packages (ADR-0050). The media package
 * binds it; without a binding, packages carry no files.
 *
 * @api
 */
interface PageFiles
{
    /** The file's contents, or null when it is gone. */
    public function read(string $file): ?string;

    /**
     * Stores $contents as one of the page's files and returns its stored name, reusing a file of
     * the page with the same name and contents. With $dryRun nothing is written.
     */
    public function write(int $pageId, string $file, string $contents, bool $dryRun = false): string;
}
