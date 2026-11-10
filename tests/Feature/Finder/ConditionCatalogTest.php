<?php

declare(strict_types=1);

use LynxCms\Finder\Catalog\ConditionCatalog;
use LynxCms\Finder\Catalog\Kind;
use LynxCms\Finder\Catalog\Subject;

/*
 * What finder conditions can look at (ConditionCatalog): the page's own properties, then the fields
 * of the templates whose pages the finder searches, each with the kind that decides its operators.
 */

beforeEach(function (): void {
    courseSite();
});

/**
 * @return array<string, string> kind by subject key
 */
function catalogKinds(): array
{
    $kinds = [];

    foreach (app(ConditionCatalog::class)->all() as $key => $subject) {
        $kinds[$key] = $subject->kind->value;
    }

    return $kinds;
}

it('offers the page properties and the fields selectors can compare', function (): void {
    expect(catalogKinds())->toMatchArray([
        'template' => Kind::Template->value,
        'parent' => Kind::Parent->value,
        'status' => Kind::Status->value,
        'modified' => Kind::Moment->value,
        'summary' => Kind::Text->value,
        'price' => Kind::Number->value,
        'related' => Kind::Pages->value,
    ]);
});

it('leaves out fields only module branches and item fields use', function (): void {
    lynx()->menus()->create('top', 'Top');
    lynx()->fields->add('loose', 'text');

    $fields = array_map(fn (Subject $subject): string => $subject->key, app(ConditionCatalog::class)->fields());

    expect($fields)->toContain('price', 'summary', 'title')
        ->not->toContain('loose')
        ->and(array_filter($fields, fn (string $name): bool => str_starts_with($name, 'menu_')))->toBe([]);
});

it('offers the templates of searched pages only', function (): void {
    lynx()->menus()->create('top', 'Top');

    expect(app(ConditionCatalog::class)->find('template')?->options)->toHaveKeys(['course', 'courses'])
        ->not->toHaveKey('menu');
});
