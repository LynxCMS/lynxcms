<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Cache\Admin\Livewire\FragmentsList;
use LynxCms\Cache\FragmentEntry;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

beforeEach(function (): void {
    installLynx();
    lynx()->languages->add('pt', 'Português', fallback: 'default');
    lynx()->cache()->remember('menu', fn (): string => '<nav class="main">Home</nav>');
    lynx()->language('pt', fn (): string => lynx()->cache()->remember('menu', fn (): string => '<nav class="main">Início</nav>'));
    lynx()->cache()->remember('footer', fn (): string => '<footer>© Lynx</footer>', dependsOn: ['template:home']);
});

it('lists stored fragments on the page cache screen', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
    $this->get(route('lynx.admin.page-cache'))->assertOk()->assertSeeLivewire(FragmentsList::class);

    Livewire::test(FragmentsList::class)
        ->assertSeeHtml('data-fragments-total="3"')
        ->assertSee('menu')->assertSee('footer')->assertSee('template:home')
        ->set('search', 'foot')->assertSee('footer')->assertDontSee('menu');
});

it('shows what a fragment holds, as source and as a sandboxed preview', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
    $id = FragmentEntry::query()->where('name', 'footer')->value('id');

    Livewire::test(FragmentsList::class)
        ->call('view', $id)
        ->assertSeeHtml('&lt;footer&gt;© Lynx&lt;/footer&gt;')
        ->assertSeeHtml('sandbox=""');
});

it('clears one fragment, every copy of a name, or all of them', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
    $footer = FragmentEntry::query()->where('name', 'footer')->value('id');

    $list = Livewire::test(FragmentsList::class)->call('clear', $footer)->assertSeeHtml('data-fragments-total="2"');
    $list->call('clearName', 'menu')->assertSeeHtml('data-fragments-total="0"');

    lynx()->cache()->remember('menu', fn (): string => 'x');
    $list->call('clearAll')->assertSeeHtml('data-fragments-total="0"');
    expect(FragmentEntry::query()->count())->toBe(0);
});

it('refuses the fragment list to users without page_cache.manage', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['writer']]);
    $this->actingAs(new GenericUser(['id' => 2]));

    Livewire::test(FragmentsList::class)->assertForbidden();
});
