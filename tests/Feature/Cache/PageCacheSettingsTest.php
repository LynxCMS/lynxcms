<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Cache\Actions\InstallPageCache;
use LynxCms\Cache\Actions\SavePageCacheRules;
use LynxCms\Cache\Admin\Livewire\PageCacheScreen;
use LynxCms\Cache\PageCacheSchema;
use LynxCms\Cache\Pages\TemplateRules;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

beforeEach(function (): void {
    config([
        'lynx-cache.pages.enabled' => true,
        'lynx-cache.pages.path' => testingPath('lynx-cache'),
    ]);
    File::deleteDirectory(testingPath('lynx-cache'));
    installLynx();
    lynx()->templates->add('note', fields: [['name' => 'title', 'required' => true]]);
    $page = lynx()->pages->new('note', '/');
    $page->name = 'a';
    $page->title = 'A';
    $page->save();
});

it('installs the settings page once', function (): void {
    lynx()->run(InstallPageCache::class);

    $settings = lynx()->settings(PageCacheSchema::SETTINGS_TEMPLATE);

    expect($settings)->toBeInstanceOf(Page::class)
        ->and($settings->hasStatus(PageStatus::SYSTEM))->toBeTrue()
        ->and(lynx()->run(InstallPageCache::class)->value)->toBeFalse();
});

it('rolls the install back when a hook halts it, and changes nothing on a dry run', function (): void {
    lynx()->run(InstallPageCache::class, [], dryRun: true);
    expect(lynx()->templates->find(PageCacheSchema::SETTINGS_TEMPLATE))->toBeNull();

    lynx()->hook(InstallPageCache::class)->after(fn () => throw new ActionHalted('No.'));
    expect(fn (): ActionResult => lynx()->run(InstallPageCache::class))->toThrow(ActionHalted::class)
        ->and(lynx()->templates->find(PageCacheSchema::SETTINGS_TEMPLATE))->toBeNull();
});

it('saves template rules that override the config', function (): void {
    config(['lynx-cache.pages.templates' => ['*' => [], 'home' => ['lifetime' => 10]]]);

    lynx()->run(SavePageCacheRules::class, [
        'lifetime' => 600,
        'templates' => [
            'note' => ['cache' => true, 'lifetime' => 60, 'parameters' => ['page'], 'clear' => ['self', 'parents']],
            'home' => ['cache' => false],
        ],
    ]);

    $note = app(TemplateRules::class)->for('note');
    expect($note?->lifetime)->toBe(60)
        ->and($note?->parameters)->toBe(['page'])
        ->and($note?->clear)->toBe(['self', 'parents'])
        ->and(app(TemplateRules::class)->for('home'))->toBeNull()
        ->and(app(TemplateRules::class)->for('other')?->lifetime)->toBe(600);
});

it('refuses unknown templates, scopes and parameter names', function (array $templates): void {
    expect(fn (): ActionResult => lynx()->run(SavePageCacheRules::class, ['templates' => $templates]))->toThrow(ValidationException::class);
})->with([
    'template' => [['nope' => ['cache' => true]]],
    'scope' => [['note' => ['cache' => true, 'clear' => ['cousins']]]],
    'parameter' => [['note' => ['cache' => true, 'parameters' => ['a b']]]],
]);

it('saves nothing on a dry run or when a hook halts the save', function (): void {
    $input = ['templates' => ['note' => ['cache' => false]]];
    lynx()->run(SavePageCacheRules::class, $input, dryRun: true);
    expect(app(TemplateRules::class)->for('note'))->not->toBeNull();

    lynx()->hook(SavePageCacheRules::class)->after(fn () => throw new ActionHalted('No.'));
    expect(fn (): ActionResult => lynx()->run(SavePageCacheRules::class, $input))->toThrow(ActionHalted::class)
        ->and(app(TemplateRules::class)->for('note'))->not->toBeNull();
});

it('edits rules, shows stored pages and clears them in the admin', function (): void {
    $this->get('/a/');
    $this->actingAs(new GenericUser(['id' => 1]));
    $this->get(route('lynx.admin.page-cache'))->assertOk()->assertSeeLivewire(PageCacheScreen::class);

    Livewire::test(PageCacheScreen::class)
        ->assertSeeHtml('data-stored-total="1"')->assertSee('localhost')->assertDontSee('value="all"', false)
        ->set('rules.note.lifetime', '120')->set('rules.note.clear', ['self'])->set('rules.note.csrf', true)
        ->call('save')->assertHasNoErrors()->assertSet('statusFor', 'rules')
        ->call('clearTemplate', 'note')->assertSeeHtml('data-stored-total="0"')->assertSet('statusFor', 'stored');

    expect(app(TemplateRules::class)->for('note')?->lifetime)->toBe(120)
        ->and(app(TemplateRules::class)->for('note')?->refreshesToken)->toBeTrue();

    Livewire::test(PageCacheScreen::class)->set('rules.note.lifetime', '-1')->call('save')->assertHasErrors();
    Livewire::test(PageCacheScreen::class)->call('clearAll')->assertHasNoErrors();
});

it('shows the server rules for the chosen server', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(PageCacheScreen::class)->set('server', 'apache')->assertSee('RewriteCond');
});

it('links the page editor to the page cache screen for a stored page', function (): void {
    $this->get('/a/');
    $this->actingAs(new GenericUser(['id' => 1]));
    $page = lynx()->pages->get('/a/');

    $this->get(route('lynx.admin.pages.edit', $page?->id))->assertSee('In the page cache')->assertSee(route('lynx.admin.page-cache', ['page' => $page?->id]), false);
    Livewire::withQueryParams(['page' => $page?->id])->test(PageCacheScreen::class)->call('clearPage')->assertSeeHtml('data-stored-total="0"');
});

it('refuses the admin screen to users without page_cache.manage', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['writer']]);
    $this->actingAs(new GenericUser(['id' => 2]));

    $this->get(route('lynx.admin.page-cache'))->assertForbidden();
    Livewire::test(PageCacheScreen::class)->assertForbidden();
});
