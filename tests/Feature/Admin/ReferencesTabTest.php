<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use LynxCms\Admin\Editor\FieldAddress;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Admin\Livewire\PageHistory;
use LynxCms\Admin\Livewire\PageReferencesTab;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Pages\Page;

/*
 * The References tab (ADR-0145): the pages that reference the page being edited, each with links
 * to the field and item that hold the reference. Field addresses open the editor there.
 */

beforeEach(function (): void {
    courseSite();
    DB::table('users')->insert(['id' => 1, 'name' => 'Ana Lima', 'email' => 'ana@example.com', 'password' => 'x']);
    $this->actingAs(new GenericUser(['id' => 1]));
    ['alpha' => $this->alpha, 'beta' => $this->beta, 'gamma' => $this->gamma] = createCourses('alpha', 'beta', 'gamma');
});

function pointAt(Page $from, Page $to): void
{
    $from->related = [(int) $to->id];
    $from->save();
}

it('lists the referencing pages with what they are and where the reference sits', function (): void {
    pointAt($this->beta, $this->alpha);

    Livewire::withoutLazyLoading()->test(PageReferencesTab::class, ['pageId' => $this->alpha->id])
        ->assertSeeHtml('data-referencing-page="'.$this->beta->id.'"')
        ->assertSeeHtml('href="'.route('lynx.admin.pages.edit', $this->beta->id).'"')
        ->assertSeeHtml('href="'.e(FieldAddress::url($this->beta, 'related')).'"')
        ->assertSee('Course under Courses');
});

it('links a reference inside a repeater item to that item', function (): void {
    lynx()->fields->add('partner', 'page', settings: ['multiple' => false]);
    lynx()->fields->add('credits', 'repeater', settings: ['fields' => [['name' => 'partner']]]);
    lynx()->templates->update('course', ['add' => ['credits']]);
    $this->beta->credits = [['values' => ['partner' => (int) $this->alpha->id]]];
    $this->beta->save();
    $key = lynx()->pages->get($this->beta->id)->getUnformatted('credits')[0]['key'];

    Livewire::withoutLazyLoading()->test(PageReferencesTab::class, ['pageId' => $this->alpha->id])
        ->assertSeeHtml('href="'.e(FieldAddress::url($this->beta, 'credits', item: $key)).'"');
});

it('shows an empty state and leaves out menu items', function (): void {
    lynx()->menus()->create('top', 'Top bar');
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => $this->alpha->id]);

    Livewire::withoutLazyLoading()->test(PageReferencesTab::class, ['pageId' => $this->alpha->id])
        ->assertSeeHtml('data-references-empty')
        ->assertDontSeeHtml('data-referencing-page=');
});

it('lists only the pages the user may view', function (): void {
    pointAt($this->beta, $this->alpha);
    pointAt($this->gamma, $this->alpha);
    lynx()->run(SaveRole::class, ['name' => 'beta-only', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view', 'branches' => [$this->beta->id]]]]);
    DB::table('users')->insert(['id' => 8, 'name' => 'Rui', 'email' => 'rui@example.com', 'password' => 'x']);
    lynx()->run(AssignRoles::class, ['user' => '8', 'roles' => ['beta-only']]);
    $this->actingAs(new GenericUser(['id' => 8]));

    Livewire::withoutLazyLoading()->test(PageReferencesTab::class, ['pageId' => $this->alpha->id])
        ->assertSeeHtml('data-referencing-page="'.$this->beta->id.'"')
        ->assertDontSeeHtml('data-referencing-page="'.$this->gamma->id.'"');
});

it('shows the pages in steps', function (): void {
    pointAt($this->beta, $this->alpha);
    pointAt($this->gamma, $this->alpha);

    Livewire::withoutLazyLoading()->test(PageReferencesTab::class, ['pageId' => $this->alpha->id, 'perPage' => 1])
        ->assertSeeHtml('data-referencing-page="'.$this->beta->id.'"')
        ->assertDontSeeHtml('data-referencing-page="'.$this->gamma->id.'"')
        ->assertSeeHtml('data-references-more')
        ->call('showMore')
        ->assertSeeHtml('data-referencing-page="'.$this->gamma->id.'"')
        ->assertDontSeeHtml('data-references-more');
});

it('opens as a tab of the editor and leaves the Settings tab', function (): void {
    Livewire::withQueryParams(['tab' => 'references'])->test(PageEditor::class, ['pageId' => $this->alpha->id])
        ->assertSeeLivewire(PageReferencesTab::class);
    Livewire::test(PageHistory::class, ['pageId' => $this->alpha->id])
        ->assertDontSeeHtml('aria-labelledby="settings-backlinks"');
});

it('builds field addresses through the admin route', function (): void {
    expect(FieldAddress::url($this->beta, 'related', item: '01JABCDEFGHJKMNPQRSTVWXYZ0', language: 'pt'))
        ->toBe(route('lynx.admin.pages.edit', ['page' => $this->beta->id, 'field' => 'related', 'item' => '01JABCDEFGHJKMNPQRSTVWXYZ0', 'lang' => 'pt']))
        ->and(FieldAddress::url((int) $this->beta->id, 'related'))->toBe(route('lynx.admin.pages.edit', ['page' => $this->beta->id, 'field' => 'related']));
});

it('opens the editor at the field a field address names', function (): void {
    lynx()->templates->update('course', ['tabs' => [['key' => 'links', 'label' => 'Links']], 'fields' => [['name' => 'title', 'required' => true], 'summary', 'body', 'price', ['name' => 'related', 'tab' => 'links']]]);

    Livewire::withQueryParams(['field' => 'related', 'lang' => 'pt'])->test(PageEditor::class, ['pageId' => $this->beta->id])
        ->assertSet('tab', 'links')
        ->assertSeeHtml('data-focus-field="related"')
        ->assertSeeHtml('data-focus-language="pt"');
});

it('opens the editor as usual for a field the template does not have', function (): void {
    Livewire::withQueryParams(['field' => 'nope'])->test(PageEditor::class, ['pageId' => $this->beta->id])
        ->assertSet('tab', 'content')
        ->assertDontSeeHtml('data-focus-field=');
});
