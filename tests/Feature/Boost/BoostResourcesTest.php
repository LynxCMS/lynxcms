<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use LynxCms\Core\Api\FieldsApi;
use LynxCms\Core\Api\LanguagesApi;
use LynxCms\Core\Api\PagesApi;
use LynxCms\Core\Api\TemplatesApi;
use LynxCms\Core\Lynx;
use LynxCms\Menus\Menus;
use Symfony\Component\Finder\Finder;

/** @return array<string, string> path => contents of every Boost guideline and skill */
function boostFiles(): array
{
    $files = [];

    foreach (Finder::create()->files()->in(dirname(__DIR__, 3).'/packages/*/resources/boost') as $file) {
        $files[$file->getRelativePathname().' ('.basename(dirname($file->getPath(), 3)).')'] = $file->getContents();
    }

    return $files;
}

it('ships skills Boost can read: a name matching the folder and a description', function (): void {
    $skills = array_filter(boostFiles(), fn (string $path): bool => str_ends_with(strtok($path, ' '), 'SKILL.md'), ARRAY_FILTER_USE_KEY);

    expect($skills)->not->toBeEmpty();

    foreach ($skills as $path => $contents) {
        preg_match('/^---\nname: ([a-z0-9-]+)\ndescription: (.+)\n---\n/', $contents, $match);

        expect($match)->not->toBeEmpty($path)
            ->and($match[1])->toBe(basename(dirname(strtok($path, ' '))), $path)
            ->and(mb_strlen($match[2]))->toBeGreaterThan(60, $path);
    }
});

it('ships guidelines that compile as Blade', function (): void {
    foreach (array_filter(boostFiles(), fn (string $path): bool => str_contains($path, 'guidelines/'), ARRAY_FILTER_USE_KEY) as $path => $contents) {
        $html = Blade::render($contents);

        expect(str_contains($html, '## Lynx CMS'))->toBeTrue($path)
            ->and(str_contains($html, '@field('))->toBeTrue("{$path}: directives must stay as text")
            ->and(str_contains($html, '<?php'))->toBeFalse($path);
    }
});

it('only mentions APIs, commands and permissions that exist', function (): void {
    $apis = ['pages' => PagesApi::class, 'languages' => LanguagesApi::class, 'fields' => FieldsApi::class, 'templates' => TemplatesApi::class];
    $commands = array_keys(Artisan::all());
    $text = implode("\n", boostFiles());

    preg_match_all('/lynx\(\)->(pages|languages|fields|templates)->(\w+)\(/', $text, $calls, PREG_SET_ORDER);
    foreach ($calls as [$call, $api, $method]) {
        expect(method_exists($apis[$api], $method))->toBeTrue($call);
    }

    preg_match_all('/lynx\(\)->menus\(\)->(\w+)\(/', $text, $menuCalls);
    foreach ($menuCalls[1] as $method) {
        expect(method_exists(Menus::class, $method))->toBeTrue("menus()->{$method}()");
    }

    preg_match_all('/lynx\(\)->(\w+)\(/', $text, $lynxCalls);
    foreach ($lynxCalls[1] as $method) {
        // Modules add theirs as macros, e.g. lynx()->menus() and lynx()->seo().
        expect(method_exists(Lynx::class, $method) || Lynx::hasMacro($method))->toBeTrue("lynx()->{$method}()");
    }

    preg_match_all('/(?<![\w:])(lynx:[a-z][a-z:-]*)/', $text, $artisan);
    foreach (array_unique($artisan[1]) as $command) {
        expect($commands)->toContain(rtrim($command, ':'));
    }

    preg_match_all('/\\\\?(LynxCms\\\\[A-Za-z\\\\]+)::class/', $text, $classes);
    foreach ($classes[1] as $class) {
        expect(class_exists($class) || interface_exists($class))->toBeTrue($class);
    }

    preg_match_all("/Gate::allows\\('lynx\\.([a-z_]+\\.[a-z_]+)'/", $text, $permissions);
    expect($permissions[1])->not->toBeEmpty();
    foreach ($permissions[1] as $permission) {
        expect(lynx()->permissions()->has($permission))->toBeTrue($permission);
    }
});
