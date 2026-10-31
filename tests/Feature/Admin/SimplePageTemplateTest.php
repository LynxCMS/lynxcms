<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageCreator;
use LynxCms\Core\Actions\InstallLynx;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Lynx;

/*
 * A new site's first page: lynx:install gives it a plain template to start from, and the New page
 * screen picks it while the parent has no pages to go by. The 404 page's template is never offered.
 */

beforeEach(function (): void {
    installLynx();
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    $this->actingAs(new GenericUser(['id' => 1]));
});

/** @return list<string> */
function simplePageFields(): array
{
    $fields = lynx()->templates->fields((string) lynx()->templates->find(InstallLynx::SIMPLE_PAGE_TEMPLATE)?->name);

    return array_values(array_map(strval(...), array_keys(collect($fields)->all())));
}

function newPageUnderHome(): Testable
{
    return Livewire::test(PageCreator::class, ['parentId' => (int) lynx()->pages->get('/')?->id]);
}

it('installs a simple page template with only a title', function (): void {
    $template = lynx()->templates->find(InstallLynx::SIMPLE_PAGE_TEMPLATE);

    expect($template)->not->toBeNull()
        ->and(simplePageFields())->toBe(['title']);
});

it('stays safe to run twice', function (): void {
    expect(fn (): Lynx => installLynx())->not->toThrow(Throwable::class);
});

it('keeps a template the site already has under that name', function (): void {
    lynx()->templates->delete(InstallLynx::SIMPLE_PAGE_TEMPLATE);
    lynx()->fields->add('intro', 'text');
    lynx()->templates->add(InstallLynx::SIMPLE_PAGE_TEMPLATE, fields: ['title', 'intro']);

    installLynx();

    expect(simplePageFields())->toBe(['title', 'intro']);
});

it('preselects it for a site\'s first page', function (): void {
    expect(newPageUnderHome()->get('template'))->toBe(InstallLynx::SIMPLE_PAGE_TEMPLATE);
});

it('never offers the page not found template', function (): void {
    expect(newPageUnderHome()->viewData('choices'))->not->toHaveKey(InstallLynx::NOT_FOUND_TEMPLATE);
});

it('still prefers what the parent\'s other pages use', function (): void {
    lynx()->templates->add('article', fields: ['title']);
    $page = lynx()->pages->new('article', '/');
    $page->name = 'first';
    $page->title = 'First';
    $page->save();

    expect(newPageUnderHome()->get('template'))->toBe('article');
});

it('falls back to the first choice on a site without it', function (): void {
    lynx()->templates->delete(InstallLynx::SIMPLE_PAGE_TEMPLATE);
    lynx()->templates->add('article', fields: ['title']);

    expect(newPageUnderHome()->get('template'))->toBe('article');
});
