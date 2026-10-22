<?php

declare(strict_types=1);

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\Core\Pages\Visibility\VariesByVisitor;
use LynxCms\Core\Pages\Visibility\VisibilityRule;
use LynxCms\Menus\Actions\RebuildMenus;
use LynxCms\Menus\Reading\Menu;
use LynxCms\Menus\Reading\MenuBuilds;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
    lynx()->menus()->create('top', ['default' => 'Top', 'pt' => 'Topo']);
    lynx()->menus()->create('footer', 'Footer');
    $this->courses = lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses', 'children' => true]);
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'link', 'label' => 'Docs', 'url' => 'https://example.com']);
    lynx()->menus()->saveItem(['menu' => 'footer', 'kind' => 'section', 'label' => 'Legal']);
});

/** Reads the menu as a new request would: no request-level caches left from the last one. */
function readStored(string $name = 'top', ?string $language = null): ?Menu
{
    app()->forgetScopedInstances();

    return $language === null ? lynx()->menus()->get($name) : lynx()->language($language, fn (): ?Menu => lynx()->menus()->get($name));
}

/** @return list<string> the SQL of the queries $read ran, besides the registries every request loads */
function queriesOf(Closure $read): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    DB::disableQueryLog();

    return array_values(array_filter(array_column(DB::getQueryLog(), 'query'), fn (string $sql): bool => preg_match('/lynx_(settings|languages|fields)\b/', $sql) !== 1));
}

function storedBuilds(): int
{
    return DB::table(MenuBuilds::BUILDS)->count();
}

it('stores the built menu on the first read and reads it back with one query', function (): void {
    $first = readStored();
    $queries = queriesOf(fn (): ?Menu => readStored());

    expect(storedBuilds())->toBe(1)
        ->and($queries)->toHaveCount(1)
        ->and($queries[0])->toContain(MenuBuilds::BUILDS)
        ->and(readStored()->toArray())->toBe($first->toArray())
        ->and(labels($first->items[0]->children))->toBe(['Alpha', 'Beta']);
});

it('builds each language separately', function (): void {
    readStored();
    $pt = readStored(language: 'pt');

    expect(storedBuilds())->toBe(2)
        ->and($pt->title)->toBe('Topo')
        ->and($pt->items[0]->label)->toBe('Cursos');
});

it('marks the current page on every request, not the one stored first', function (): void {
    app()->instance('request', Request::create('/courses/alpha'));
    $first = readStored()->items[0];
    app()->instance('request', Request::create('/courses'));
    $second = readStored()->items[0];

    expect([$first->isActive, $first->isInActiveTrail, $first->children[0]->isActive])->toBe([false, true, true])
        ->and([$second->isActive, $second->children[0]->isActive])->toBe([true, false]);
});

it('builds again after its items change', function (): void {
    readStored();
    lynx()->menus()->saveItem(['menu' => 'top', 'item' => $this->courses->id, 'kind' => 'page', 'page' => '/courses', 'label' => 'All courses', 'children' => true]);
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'section', 'label' => 'New']);

    expect(labels(readStored()->items))->toBe(['All courses', 'Docs', 'New']);
});

it('builds again after a page it shows changes', function (): void {
    readStored();
    $courses = lynx()->pages->get('/courses');
    $courses->title = 'Programmes';
    lynx()->pages->save($courses);

    expect(readStored()->items[0]->label)->toBe('Programmes');
});

it('builds again when the children it lists change', function (Closure $change, array $labels): void {
    readStored();
    $change();

    expect(labels(readStored()->items[0]->children))->toBe($labels);
})->with([
    'a new child' => [fn (): array => createCourses('gamma'), ['Alpha', 'Beta', 'Gamma']],
    'a child hidden' => [fn () => lynx()->pages->setStatus('/courses/beta', add: ['hidden']), ['Alpha']],
    'a hidden child shown again' => [function (): void {
        lynx()->pages->setStatus('/courses/beta', add: ['hidden']);
        readStored();
        lynx()->pages->setStatus('/courses/beta', remove: ['hidden']);
    }, ['Alpha', 'Beta']],
]);

it('builds again when a page above a linked page is unpublished', function (): void {
    lynx()->menus()->saveItem(['menu' => 'footer', 'kind' => 'page', 'page' => '/courses/alpha']);
    readStored('footer');
    lynx()->pages->setStatus('/courses', add: ['unpublished']);

    expect(labels(readStored('footer')->items))->toBe(['Legal']);
});

it('keeps the stored menu when unrelated pages change', function (): void {
    readStored('footer');
    $alpha = lynx()->pages->get('/courses/alpha');
    $alpha->summary = 'Changed';
    lynx()->pages->save($alpha);

    expect(queriesOf(fn (): ?Menu => readStored('footer')))->toHaveCount(1);
});

it('builds every menu again after a change that may touch every page, or a schema change', function (Closure $change): void {
    readStored();
    readStored('footer');
    $change();

    expect(storedBuilds())->toBe(0);
})->with([
    'a new language' => [fn () => lynx()->languages->add('de', 'Deutsch', locale: 'de-DE', fallback: 'default')],
    'a new field' => [fn (): ActionResult => lynx()->run(CreateField::class, ['name' => 'subtitle', 'type' => 'text'])],
]);

it('reads live, without storing, for visitors a visibility rule treats apart', function (bool $variesByVisitor): void {
    app(PageVisibility::class)->add($variesByVisitor ? new SeesMore : new UnknownRule);

    $menu = readStored();

    expect(labels($menu->items))->toBe(['Courses', 'Docs'])
        ->and(storedBuilds())->toBe(0);
})->with(['a rule that says the visitor sees more' => true, 'a rule that does not say' => false]);

it('rebuilds one menu or all of them by hand', function (): void {
    readStored();
    readStored('footer');

    expect(lynx()->run(RebuildMenus::class, ['menu' => 'top'])->value)->toBe(1)
        ->and(DB::table(MenuBuilds::BUILDS)->pluck('menu_name')->all())->toBe(['footer'])
        ->and(lynx()->run(RebuildMenus::class, [])->value)->toBe(1)
        ->and(storedBuilds())->toBe(0);
});

it('refuses to rebuild a menu that does not exist', function (): void {
    lynx()->run(RebuildMenus::class, ['menu' => 'nope']);
})->throws(ValidationException::class);

it('lists what a rebuild would drop on a dry run and keeps it', function (): void {
    readStored();

    $result = lynx()->run(RebuildMenus::class, ['menu' => 'top'], dryRun: true);

    expect($result->changes->all()[0]->summary)->toContain('top')
        ->and(storedBuilds())->toBe(1);
});

it('keeps the stored menus when a hook halts the rebuild', function (): void {
    readStored();
    lynx()->hook(RebuildMenus::class)->after(fn () => throw new ActionHalted('No.'));

    expect(fn (): ActionResult => lynx()->run(RebuildMenus::class, []))->toThrow(ActionHalted::class)
        ->and(storedBuilds())->toBe(1);
});

final class SeesMore implements VariesByVisitor, VisibilityRule
{
    public function apply(Builder $query): void {}

    public function allows(Page $page): bool
    {
        return true;
    }

    public function audience(): string
    {
        return 'unlocked:1';
    }
}

final class UnknownRule implements VisibilityRule
{
    public function apply(Builder $query): void {}

    public function allows(Page $page): bool
    {
        return true;
    }
}
