<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageLoader;
use Psy\VarDumper\Presenter;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

/*
 * tinker and Tinkerwell (through tinker.casters), var_dump() and print_r() show a page's own data,
 * not the loader and registries it points to, and looking never loads a field.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $alpha] = createCourses('alpha');
    $alpha->setLanguageName('pt', 'alfa');
    $alpha->price = 120;
    $alpha->save();
    $this->id = (int) $alpha->id;
    app(PageLoader::class)->flush();
});

function freshAlpha(): Page
{
    return lynx()->pages->get(test()->id) ?? throw new RuntimeException('No alpha.');
}

it('shows the page, its names and loaded values by language, and the fields not loaded yet', function (): void {
    $info = freshAlpha()->__debugInfo();

    expect($info['id'])->toBe($this->id)
        ->and($info['template'])->toBe('course')
        ->and($info['names'])->toHaveKeys(['default', 'pt'])
        ->and($info['values'])->toBe(['title' => ['default' => 'Alpha']])
        ->and($info['notLoaded'])->toContain('summary', 'price', 'related');
});

it('lists a field among the values once it is read', function (): void {
    $page = freshAlpha();
    $page->price;

    expect($page->__debugInfo()['values']['price'] ?? null)->toBe(['*' => 120])
        ->and($page->__debugInfo()['notLoaded'])->not->toContain('price');
});

it('looks without running a query', function (): void {
    $page = freshAlpha();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $page->__debugInfo();
    DB::disableQueryLog();

    expect(DB::getQueryLog())->toBe([]);
});

it('keeps the loader and the registries out of what tinker shows', function (): void {
    $presenter = new Presenter(new OutputFormatter(false));
    $presenter->addCasters((array) config('tinker.casters'));
    $dump = $presenter->present(freshAlpha(), null, Presenter::VERBOSE | Presenter::RAW);

    // The page's batch of fields not loaded yet leads to its loader and both registries.
    expect($dump)->toContain('notLoaded')
        ->not->toContain('DeferredFields');
});

it('keeps the loader and the registries out of dump(), dd() and Tinkerwell, which use no caster', function (): void {
    $dump = (string) (new CliDumper)->dump((new VarCloner)->cloneVar(freshAlpha()), true);

    expect($dump)->toContain('notLoaded')
        ->not->toContain('PageLoader')
        ->not->toContain('TemplateRegistry');
});

it('still loads a field for every page of the batch in one query', function (): void {
    createCourses('beta', 'gamma');
    app(PageLoader::class)->flush();
    $courses = lynx()->pages->find('template=course');
    DB::flushQueryLog();
    DB::enableQueryLog();
    $courses->first()?->summary;
    DB::disableQueryLog();

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and($courses->map(fn (Page $page): bool => $page->isDeferred('summary')))->toBe([false, false, false]);
});
