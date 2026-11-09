<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Menus\Actions\InstallMenus;
use LynxCms\Menus\MenuSchema;

/*
 * Menus hold menus, and menus and their items hold items only (template family rules), so a site
 * page can never be moved or created under a menu item, where it would leave the site.
 */

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    lynx()->menus()->create('top', 'Top');
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'link', 'label' => 'Courses', 'url' => 'https://example.com']);
});

function firstMenuItemId(): int
{
    return (int) lynx()->pages->findOne('template='.MenuSchema::ITEM_TEMPLATE.', include=all')?->id;
}

it('refuses to move a page under a menu item', function (): void {
    expect(fn () => lynx()->pages->move('/courses/alpha/', firstMenuItemId()))->toThrow(ValidationException::class)
        ->and(childNamesOf())->toBe(['alpha']);
});

it('still nests menu items', function (): void {
    lynx()->menus()->saveItem(['menu' => 'top', 'parent' => firstMenuItemId(), 'kind' => 'link', 'label' => 'Child', 'url' => 'https://example.com/child']);

    expect(lynx()->menus()->get('top')->items[0]->children)->toHaveCount(1);
});

it('gives menus installed before the rules their family rules', function (): void {
    lynx()->templates->update(MenuSchema::ITEM_TEMPLATE, ['child_templates' => [], 'parent_templates' => []]);

    lynx()->run(InstallMenus::class);

    expect(lynx()->templates->get(MenuSchema::ITEM_TEMPLATE)->childTemplates())->toBe([MenuSchema::ITEM_TEMPLATE]);
});
