<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Lynx;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Menus\Actions\CreateMenu;
use LynxCms\Menus\Actions\InstallMenus;
use LynxCms\Menus\Actions\SaveMenuItem;

beforeEach(fn (): Lynx => courseSite());

it('installs the menu schema and root once', function (): void {
    lynx()->run(InstallMenus::class);
    lynx()->run(InstallMenus::class);

    $root = lynx()->pages->findOne('template=menus, include=all');

    expect($root)->toBeInstanceOf(Page::class)
        ->and($root->hasStatus(PageStatus::SYSTEM))->toBeTrue()
        ->and($root->hasStatus(PageStatus::HIDDEN))->toBeTrue()
        ->and(lynx()->pages->count('template=menus, include=all'))->toBe(1)
        ->and(lynx()->templates->get('menu-item')->hasUrl())->toBeFalse();
});

it('adds fields introduced later to existing menu templates', function (): void {
    lynx()->run(InstallMenus::class);
    lynx()->templates->update('menu-item', ['remove' => ['menu_limit']]);

    lynx()->run(InstallMenus::class);

    expect(lynx()->templates->fields('menu-item')->keys()->all())->toContain('menu_limit')
        ->and(lynx()->run(InstallMenus::class)->value)->toBeFalse();
});

it('creates menus with translated titles and installs on first use', function (): void {
    $menu = lynx()->menus()->create('top', ['default' => 'Top bar', 'pt' => 'Barra superior']);

    expect($menu->name())->toBe('top')
        ->and((string) $menu->title)->toBe('Top bar')
        ->and(lynx()->language('pt', fn (): string => (string) lynx()->pages->get($menu->id)->title))->toBe('Barra superior');
});

it('rejects a duplicate or invalid menu name', function (string $name): void {
    lynx()->menus()->create('top', 'Top');

    expect(fn () => lynx()->menus()->create($name, 'Again'))->toThrow(ValidationException::class);
})->with(['top', 'Top Bar', '-x', '']);

it('adds a section label in several languages', function (): void {
    lynx()->menus()->create('footer', 'Footer');

    $item = lynx()->menus()->saveItem(['menu' => 'footer', 'kind' => 'section', 'label' => ['default' => 'Company', 'pt' => 'Empresa']]);

    expect((string) $item->title)->toBe('Company')
        ->and($item->template()->name)->toBe('menu-item')
        ->and($item->parentId)->toBe(lynx()->menus()->page('footer')?->id);
});

it('adds a page item with automatic children', function (): void {
    lynx()->menus()->create('top', 'Top');
    $courses = lynx()->pages->get('/courses/');

    $item = lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses/', 'children' => true]);

    expect($item->menu_page?->id)->toBe($courses->id)
        ->and($item->menu_children)->toBeTrue()
        ->and((string) $item->title)->toBe('');
});

it('adds a custom link under another item', function (): void {
    lynx()->menus()->create('top', 'Top');
    $section = lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'section', 'label' => 'Resources']);

    $link = lynx()->menus()->saveItem([
        'menu' => 'top',
        'parent' => $section->id,
        'kind' => 'link',
        'label' => 'Docs',
        'url' => ['default' => 'https://example.com/docs', 'pt' => 'https://example.com/pt/docs'],
        'target' => '_blank',
        'class' => 'is-external',
    ]);

    expect($link->parentId)->toBe($section->id)
        ->and((string) $link->menu_url)->toBe('https://example.com/docs')
        ->and($link->getUnformatted('menu_target'))->toBe('_blank')
        ->and($link->getUnformatted('menu_class'))->toBe('is-external');
});

it('updates an item and switches its kind, clearing the other kind\'s values', function (): void {
    lynx()->menus()->create('top', 'Top');
    $item = lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'link', 'label' => 'Docs', 'url' => '/docs/']);

    $updated = lynx()->menus()->saveItem(['item' => $item->id, 'kind' => 'page', 'page' => '/courses/']);

    expect($updated->id)->toBe($item->id)
        ->and($updated->menu_page?->id)->toBe(lynx()->pages->get('/courses/')->id)
        ->and((string) $updated->menu_url)->toBe('');
});

it('validates items', function (array $input, string $field): void {
    lynx()->menus()->create('top', 'Top');
    lynx()->menus()->create('footer', 'Footer');
    $footerItem = lynx()->menus()->saveItem(['menu' => 'footer', 'kind' => 'section', 'label' => 'X']);
    $input = array_map(fn ($v) => $v === 'FOOTER_ITEM' ? $footerItem->id : $v, $input);

    try {
        lynx()->menus()->saveItem(['menu' => 'top', ...$input]);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
})->with([
    'section without label' => [['kind' => 'section'], 'label'],
    'unknown kind' => [['kind' => 'video', 'label' => 'X'], 'kind'],
    'page missing' => [['kind' => 'page'], 'page'],
    'page does not exist' => [['kind' => 'page', 'page' => 9999], 'page'],
    'link without url' => [['kind' => 'link', 'label' => 'X'], 'url'],
    'link without label' => [['kind' => 'link', 'url' => '/x/'], 'label'],
    'javascript url' => [['kind' => 'link', 'label' => 'X', 'url' => 'javascript:alert(1)'], 'url'],
    'protocol-relative url' => [['kind' => 'link', 'label' => 'X', 'url' => '//evil.example/phish'], 'url'],
    'backslash url' => [['kind' => 'link', 'label' => 'X', 'url' => '\\\\evil.example'], 'url'],
    'slash backslash url' => [['kind' => 'link', 'label' => 'X', 'url' => '/\\evil.example'], 'url'],
    'unknown language' => [['kind' => 'section', 'label' => 'X', 'languages' => ['xx']], 'languages'],
    'no language' => [['kind' => 'section', 'label' => 'X', 'languages' => []], 'languages'],
    'icon with markup' => [['kind' => 'section', 'label' => 'X', 'icon_before' => '"><script>'], 'icon_before'],
    'item of another menu' => [['item' => 'FOOTER_ITEM', 'kind' => 'section', 'label' => 'X'], 'item'],
    'javascript url in a language' => [['kind' => 'link', 'label' => 'X', 'url' => ['default' => '/x/', 'pt' => ' JavaScript:alert(1)']], 'url'],
    'bad target' => [['kind' => 'link', 'label' => 'X', 'url' => '/x/', 'target' => '_blank" onclick="x'], 'target'],
    'parent in another menu' => [['kind' => 'section', 'label' => 'X', 'parent' => 'FOOTER_ITEM'], 'parent'],
    'unknown menu' => [['menu' => 'nope', 'kind' => 'section', 'label' => 'X'], 'menu'],
]);

it('accepts safe url forms', function (string $url): void {
    lynx()->menus()->create('top', 'Top');

    expect(lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'link', 'label' => 'X', 'url' => $url]))->toBeInstanceOf(Page::class);
})->with(['https://example.com', 'http://example.com/a?b=c', '/relative/', '#top', 'mailto:hi@example.com', 'tel:+351123456']);

it('reports changes on a dry run and writes nothing', function (): void {
    lynx()->menus()->create('top', 'Top');
    $before = DB::table('lynx_pages')->count();

    $result = lynx()->run(SaveMenuItem::class, ['menu' => 'top', 'kind' => 'section', 'label' => 'X'], dryRun: true);

    expect($result)->toBeInstanceOf(ActionResult::class)
        ->and($result->changes->count())->toBeGreaterThan(0)
        ->and(DB::table('lynx_pages')->count())->toBe($before);
});

it('rolls back a new menu when a hook halts', function (): void {
    lynx()->hook(CreateMenu::class)->after(fn () => throw new ActionHalted('No.'));

    expect(fn () => lynx()->menus()->create('top', 'Top'))->toThrow(ActionHalted::class)
        ->and(lynx()->menus()->page('top'))->toBeNull();
});

it('refuses an existing menu field with the wrong translation setting', function (): void {
    lynx()->fields->add('menu_target', 'text', translatable: true);

    expect(fn (): ActionResult => lynx()->run(InstallMenus::class))->toThrow(ValidationException::class, 'menu_target');
});
