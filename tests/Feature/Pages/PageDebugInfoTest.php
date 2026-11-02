<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageLoader;
use Psy\VarDumper\Presenter;
use Symfony\Component\Console\Formatter\OutputFormatter;

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
