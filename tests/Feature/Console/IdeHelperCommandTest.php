<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
 * lynx:ide-helper writes a stub file editors index, so $page->price and $item->caption
 * autocomplete with the types lynx:phpstan gives them. The file is never run.
 */

beforeEach(function (): void {
    courseSite();
    $this->file = testingPath('ide-helper').'/_ide_helper_lynx.php';
    File::deleteDirectory(dirname($this->file));
});

afterEach(function (): void {
    File::deleteDirectory(dirname($this->file));
});

it('writes each field as a property of pages and items, with its type', function (): void {
    $this->artisan('lynx:ide-helper', ['--path' => $this->file])->assertSuccessful();
    $stub = File::get($this->file);

    expect($stub)->toContain('namespace LynxCms\Core\Pages {', 'class Page', 'class Item')
        ->toMatch('/@property int\|null \$price\b/')
        ->toMatch('/@property \\\\LynxCms\\\\Core\\\\Pages\\\\TranslatableValue\|null \$summary\b/')
        ->toMatch('/@property-read string \$url\b/');
});

it('writes a file PHP can parse, that stops before declaring anything', function (): void {
    $this->artisan('lynx:ide-helper', ['--path' => $this->file])->assertSuccessful();

    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($this->file), result_code: $lint);
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($this->file), $output, $run);

    expect($lint)->toBe(0)
        ->and($run)->toBe(0)
        ->and($output)->toHaveCount(1);
});
