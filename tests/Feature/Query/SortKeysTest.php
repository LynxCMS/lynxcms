<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Query\PageFinder;
use LynxCms\Core\Support\Tables;

/*
 * ADR-0061: sorting by a translatable field named in lynx.sort_keys reads stored sort keys, the
 * value visitors see in each language, instead of sorting a COALESCE.
 */

beforeEach(function (): void {
    courseSite();

    // [name, default title, Portuguese title]
    foreach ([['a', 'Banana', 'Abacate'], ['b', 'Cherry', null], ['c', 'Apple', 'Zebra']] as [$name, $title, $ptTitle]) {
        $page = lynx()->pages->new('course', '/courses/');
        $page->name = $name;
        $page->title = $title;
        if ($ptTitle !== null) {
            $page->setLanguageValue('pt', 'title', $ptTitle);
        }
        $page->save();
    }
});

$order = fn (string $language, string $sort = 'title'): array => lynx()->language($language, fn (): array => lynx()->pages->find("template=course, sort={$sort}")->map(fn (Page $page): string => (string) $page->name));

it('sorts by the value each language shows', function () use ($order): void {
    expect($order('pt'))->toBe(['a', 'b', 'c'])
        ->and($order('default'))->toBe(['c', 'a', 'b'])
        ->and($order('pt', '-title'))->toBe(['c', 'b', 'a']);
});

it('reads the sort keys for configured fields only', function (): void {
    $sql = fn (string $sort): string => app(PageFinder::class)->compile("template=course, sort={$sort}")->toSql();

    expect($sql('title'))->toContain(Tables::SORT_KEYS)
        ->and($sql('summary'))->not->toContain(Tables::SORT_KEYS);
});

it('follows a changed fallback value', function () use ($order): void {
    $b = lynx()->pages->get('/courses/b/');
    $b->title = 'Aardvark';
    $b->save();

    expect($order('pt'))->toBe(['b', 'a', 'c']);
});

it('follows a new language and a changed fallback', function (): void {
    lynx()->languages->add('es', 'Español', fallback: 'pt');
    $a = lynx()->pages->get('/courses/a/');
    $key = fn (): mixed => DB::table(Tables::SORT_KEYS)->where('page_id', $a?->id)->where('language_id', lynx()->languages->get('es')->id)->value('value');
    $before = $key();
    lynx()->languages->update('es', ['fallback' => '']);

    expect($before)->toBe('Abacate')->and($key())->toBe('Banana');
});

it('drops the keys of deleted pages', function (): void {
    $c = lynx()->pages->get('/courses/c/');
    lynx()->pages->delete((int) $c?->id);

    expect(DB::table(Tables::SORT_KEYS)->where('page_id', $c?->id)->count())->toBe(0);
});

it('rebuilds every key with lynx:sort-keys', function () use ($order): void {
    DB::table(Tables::SORT_KEYS)->delete();

    $this->artisan('lynx:sort-keys')->assertSuccessful();

    expect($order('pt'))->toBe(['a', 'b', 'c']);
});

it('compares the effective value until a newly configured field has its keys', function () use ($order): void {
    config(['lynx.sort_keys' => ['title', 'summary']]);
    $sql = fn (): string => app(PageFinder::class)->compile('template=course, sort=summary')->toSql();
    $without = $sql();

    $this->artisan('lynx:sort-keys')->assertSuccessful();

    expect($without)->not->toContain(Tables::SORT_KEYS)
        ->and($sql())->toContain(Tables::SORT_KEYS)
        ->and($order('pt', 'summary'))->toHaveCount(3);
});
