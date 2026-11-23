<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * docs/reference/public-api.md lists every @api class. tools/docs/public-api.php writes it from
 * the code; this fails when a class gained or lost the marker, or its summary changed, until
 * composer docs:api writes the page again.
 */

it('lists every @api class as the code marks them', function (): void {
    $root = dirname(__DIR__, 2);
    $check = new Process([PHP_BINARY, $root.'/tools/docs/public-api.php', '--check'], $root);
    $check->run();

    expect($check->getExitCode())->toBe(0, $check->getErrorOutput());
});
