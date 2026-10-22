<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Menus\Reading\Menu;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
    lynx()->menus()->create('top', ['default' => 'Top', 'pt' => 'Topo']);
});

it('returns null for a missing menu', function (): void {
    expect(lynx()->menus()->get('nope'))->toBeNull()
        ->and(lynx()->menus()->get('../x'))->toBeNull();
});

it('resolves the three kinds in order', function (): void {
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses']);
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'section', 'label' => 'Resources']);
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'link', 'label' => 'Docs', 'url' => 'https://example.com', 'target' => '_blank', 'class' => 'external']);

    $menu = lynx()->menus()->get('top');
    [$page, $section, $link] = $menu->items;

    expect($menu)->toBeInstanceOf(Menu::class)
        ->and($menu->title)->toBe('Top')
        ->and(labels($menu->items))->toBe(['Courses', 'Resources', 'Docs'])
        ->and([$page->kind, $page->url])->toBe(['page', '/courses'])
        ->and([$section->kind, $section->url, $section->hasUrl()])->toBe(['section', null, false])
        ->and([$link->kind, $link->url, $link->target, $link->rel, $link->class])->toBe(['link', 'https://example.com', '_blank', 'noopener', 'external']);
});

it('uses labels and page titles of the current language with fallback', function (): void {
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses']);
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'section', 'label' => ['default' => 'Company', 'pt' => 'Empresa']]);
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'section', 'label' => 'Only English']);

    $menu = lynx()->language('pt', fn () => lynx()->menus()->get('top'));

    expect($menu->title)->toBe('Topo')
        ->and(labels($menu->items))->toBe(['Cursos', 'Empresa', 'Only English']);
});

it('nests manual items and adds child pages automatically', function (): void {
    $courses = lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses', 'label' => 'All courses', 'children' => true]);
    lynx()->menus()->saveItem(['menu' => 'top', 'parent' => $courses->id, 'kind' => 'link', 'label' => 'Calendar', 'url' => '/calendar']);

    $item = lynx()->menus()->get('top')->items[0];

    expect($item->label)->toBe('All courses')
        ->and(labels($item->children))->toBe(['Calendar', 'Alpha', 'Beta'])
        ->and($item->children[1]->id)->toBeNull()
        ->and($item->children[1]->depth)->toBe(1);
});

it('limits automatic children to each item\'s depth', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha');
    $sub = lynx()->pages->new('course', $alpha);
    $sub->name = 'sub';
    $sub->title = 'Sub';
    $sub->save();
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses', 'children' => true]);
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses', 'children' => true, 'depth' => 2]);

    [$shallow, $deep] = lynx()->menus()->get('top')->items;

    expect($shallow->children[0]->children)->toBe([])
        ->and(labels($deep->children[0]->children))->toBe(['Sub']);
});

it('leaves out pages that are hidden, unpublished or below an unpublished page', function (): void {
    $lesson = lynx()->pages->new('course', '/courses/beta');
    $lesson->name = 'lesson';
    $lesson->title = 'Lesson';
    $lesson->save();
    createCourses('gamma');
    foreach (['alpha', 'beta', 'beta/lesson', 'gamma'] as $path) {
        lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => "/courses/{$path}"]);
    }
    lynx()->pages->setStatus('/courses/alpha', add: ['hidden']);
    lynx()->pages->setStatus('/courses/beta', add: ['unpublished']);

    expect(labels(lynx()->menus()->get('top')->items))->toBe(['Gamma']);
});

it('leaves hidden child pages out of automatic children', function (): void {
    lynx()->pages->setStatus('/courses/alpha', add: ['hidden']);
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses', 'children' => true]);

    expect(labels(lynx()->menus()->get('top')->items[0]->children))->toBe(['Beta']);
});

it('shows an item only in the languages chosen for it', function (): void {
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'section', 'label' => 'Everywhere']);
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'section', 'label' => ['default' => 'English only'], 'languages' => ['default']]);
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'section', 'label' => ['default' => 'Portuguese', 'pt' => 'Só em português'], 'languages' => ['pt']]);

    expect(labels(lynx()->menus()->get('top')->items))->toBe(['Everywhere', 'English only'])
        ->and(labels(lynx()->language('pt', fn () => lynx()->menus()->get('top'))->items))->toBe(['Everywhere', 'Só em português']);
});

it('changes the languages of an existing item', function (): void {
    $item = lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'section', 'label' => 'X', 'languages' => ['pt']]);

    lynx()->menus()->saveItem(['item' => $item->id, 'kind' => 'section', 'languages' => ['default', 'pt']]);

    expect(labels(lynx()->menus()->get('top')->items))->toBe(['X']);
});

it('leaves out pages that are not available in the current language', function (): void {
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses']);
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses/alpha']);

    // Alpha has no Portuguese name, so /pt/ does not serve it.
    expect(labels(lynx()->language('pt', fn () => lynx()->menus()->get('top'))->items))->toBe(['Cursos']);
});

it('resolves icons before and after the label', function (): void {
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'link', 'label' => 'Docs', 'url' => '/docs', 'icon_before' => 'fa fa-book', 'icon_after' => 'icon-external']);

    $item = lynx()->menus()->get('top')->items[0];

    expect([$item->iconBefore, $item->iconAfter])->toBe(['fa fa-book', 'icon-external'])
        ->and($item->toArray())->toMatchArray(['icon_before' => 'fa fa-book', 'icon_after' => 'icon-external']);
});

it('marks the current page and its trail as active', function (): void {
    $courses = lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses', 'children' => true]);
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'link', 'label' => 'Elsewhere', 'url' => 'https://other.example/courses/alpha']);
    $this->get('/courses/alpha');

    $menu = lynx()->menus()->get('top');

    expect([$menu->items[0]->isActive, $menu->items[0]->isInActiveTrail])->toBe([false, true])
        ->and($menu->items[0]->children[0]->isActive)->toBeTrue()
        ->and($menu->items[1]->isInActiveTrail)->toBeFalse();
});

it('exports the menu as an array for headless use', function (): void {
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'link', 'label' => 'Docs', 'url' => '/docs']);

    expect(lynx()->menus()->get('top')->toArray())->toMatchArray([
        'name' => 'top',
        'title' => 'Top',
        'items' => [['id' => lynx()->menus()->get('top')->items[0]->id, 'kind' => 'link', 'label' => 'Docs', 'url' => '/docs', 'target' => null, 'rel' => null, 'class' => null, 'depth' => 0, 'active' => false, 'active_trail' => false, 'page_id' => null, 'icon_before' => null, 'icon_after' => null, 'children' => []]],
    ]);
});

it('caps automatic child pages at each item\'s limit', function (): void {
    createCourses('gamma', 'delta');
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses', 'children' => true, 'limit' => 3]);
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses', 'children' => true]);

    [$short, $all] = lynx()->menus()->get('top')->items;

    expect(labels($short->children))->toBe(['Alpha', 'Beta', 'Gamma'])
        ->and($all->children)->toHaveCount(4);
});

it('rejects a depth or limit out of range', function (string $key, int $value): void {
    expect(fn () => lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses', 'children' => true, $key => $value]))
        ->toThrow(ValidationException::class);
})->with([['depth', 0], ['depth', 11], ['limit', 0], ['limit', 101]]);

it('clears depth and limit when an item no longer shows child pages', function (): void {
    $item = lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses', 'children' => true, 'depth' => 2, 'limit' => 5]);

    $saved = lynx()->menus()->saveItem(['item' => $item->id, 'kind' => 'page', 'page' => '/courses', 'children' => false, 'depth' => 2, 'limit' => 5]);

    expect([$saved->menu_depth, $saved->menu_limit])->toBe([null, null]);
});

it('never marks fragment links as the current page', function (): void {
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'link', 'label' => 'Top of page', 'url' => '#top']);
    $this->get('/');

    expect(lynx()->menus()->get('top')->items[0]->isActive)->toBeFalse();
});

it('shows at most 10 child pages per level by default', function (): void {
    createCourses(...array_map(fn (int $i): string => "extra-{$i}", range(1, 10)));
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses', 'children' => true]);

    expect(lynx()->menus()->get('top')->items[0]->children)->toHaveCount(10);
});
