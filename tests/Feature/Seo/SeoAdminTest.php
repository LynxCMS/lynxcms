<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Seo\Actions\EnableSeo;
use LynxCms\Seo\Admin\Livewire\SeoAudit;
use LynxCms\Seo\Admin\Livewire\SeoSchemas;
use LynxCms\Seo\Admin\Livewire\SeoTemplates;
use LynxCms\Seo\JsonLd\Mappings;
use LynxCms\Seo\SeoSchema;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('turns SEO on and off per template', function (): void {
    $this->get(route('lynx.admin.seo'))->assertOk()->assertSeeLivewire(SeoTemplates::class);

    Livewire::test(SeoTemplates::class)->assertSee('course')->assertDontSee(SeoSchema::SETTINGS_TEMPLATE)
        ->call('toggle', 'course')->assertHasNoErrors();
    expect(lynx()->templates->fields('course')->keys()->all())->toContain(SeoSchema::TITLE);

    Livewire::test(SeoTemplates::class)->call('toggle', 'course');
    expect(lynx()->templates->fields('course')->keys()->all())->not->toContain(SeoSchema::TITLE);
});

it('edits the settings in the page editor, out of the page tree', function (): void {
    lynx()->run(EnableSeo::class, ['template' => 'course']);
    $settings = lynx()->settings(SeoSchema::SETTINGS_TEMPLATE);

    $this->get(route('lynx.admin.seo'))->assertSee(route('lynx.admin.pages.edit', $settings?->id));
    $this->get(route('lynx.admin.pages.edit', $settings?->id))->assertOk()->assertSee('Site name');
    Livewire::test(PageTree::class)->assertDontSee('SEO');
});

it('previews a page as a search result and a social card', function (): void {
    lynx()->run(EnableSeo::class, ['template' => 'course']);
    $alpha = lynx()->pages->get('/courses/alpha');
    lynx()->run(SavePage::class, ['id' => $alpha->id, 'values' => [SeoSchema::DESCRIPTION => ['default' => 'All about alpha.']]]);

    $this->get(route('lynx.admin.pages.edit', $alpha->id))->assertSee(route('lynx.admin.seo.preview', $alpha->id));
    $this->get(route('lynx.admin.seo.preview', $alpha->id))->assertOk()
        ->assertSee('All about alpha.')->assertSee('localhost › courses › alpha');
});

it('lists pages with a missing description or a title used twice', function (): void {
    lynx()->run(SavePage::class, ['id' => lynx()->pages->get('/courses/beta')->id, 'values' => ['title' => ['default' => 'Alpha'], 'summary' => ['default' => 'Beta things.']]]);

    $this->get(route('lynx.admin.seo.audit'))->assertOk()->assertSeeLivewire(SeoAudit::class);

    Livewire::test(SeoAudit::class)
        ->assertSee('/courses/alpha')->assertSee('No description')
        ->assertSee('Same title as 1 other page');
});

it('maps a template to a schema type, with suggested fields', function (): void {
    $this->get(route('lynx.admin.seo.schemas'))->assertOk()->assertSeeLivewire(SeoSchemas::class);

    Livewire::test(SeoSchemas::class)
        ->call('create')->set('template', 'course')->set('type', 'Course')
        ->assertSee('provider.name')->assertSee('<option value="summary">', false)
        ->set('sources.name', '@title')->set('sources.offers__price', 'price')->set('sources.offers__priceCurrency', '=EUR')
        ->call('save')->assertHasNoErrors()->assertSet('editing', null)->assertSee('Course');

    expect(app(Mappings::class)->for('course')?->sources)->toBe(['name' => '@title', 'offers.price' => 'price', 'offers.priceCurrency' => '=EUR']);

    Livewire::test(SeoSchemas::class)->call('edit', 0)->assertSet('sources.offers__price', 'price')->call('remove', 0);
    expect(app(Mappings::class)->for('course'))->toBeNull();
});

it('shows why a mapping is refused and keeps the form open', function (): void {
    Livewire::test(SeoSchemas::class)
        ->call('create')->set('template', 'course')->set('type', 'Course')->set('sources.image', 'summary')
        ->call('save')->assertHasErrors('mapping')->assertSee('needs an image field')->assertSet('editing', 0);

    expect(app(Mappings::class)->for('course'))->toBeNull();
});

it('refuses users without seo.manage', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['writer']]);
    $this->actingAs(new GenericUser(['id' => 2]));

    $this->get(route('lynx.admin.seo'))->assertForbidden();
    $this->get(route('lynx.admin.seo.audit'))->assertForbidden();
    $this->get(route('lynx.admin.seo.schemas'))->assertForbidden();
    Livewire::test(SeoTemplates::class)->assertForbidden();
});
