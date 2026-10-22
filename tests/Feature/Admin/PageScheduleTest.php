<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageSchedule;
use LynxCms\Admin\Livewire\PageSettings;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    test()->actingAs(new GenericUser(['id' => 1]));
});

function alphaId(): int
{
    return (int) lynx()->pages->get('/courses/alpha/')?->id;
}

it('shows the schedule in the page settings', function (): void {
    Livewire::test(PageSettings::class, ['pageId' => alphaId()])->assertSee('Schedule');
});

it('saves publish and unpublish times per language', function (): void {
    $publish = now()->addDay()->format('Y-m-d\TH:i');
    $unpublish = now()->addWeek()->format('Y-m-d\TH:i');

    Livewire::test(PageSchedule::class, ['pageId' => alphaId()])
        ->set('perLanguage', true)
        ->set('times.pt.publish', $publish)
        ->set('times.default.unpublish', $unpublish)
        ->call('saveSchedule')
        ->assertHasNoErrors()
        ->assertDispatched('lynx-notify', message: 'Schedule saved.');

    expect(array_map(fn (array $entry): array => [$entry['language'], $entry['action'], $entry['at']->format('Y-m-d\TH:i')], lynx()->pages->schedules(alphaId())))
        ->toBe([['default', 'unpublish', $unpublish], ['pt', 'publish', $publish]]);
});

it('opens on one row for every language when nothing is scheduled', function (): void {
    Livewire::test(PageSchedule::class, ['pageId' => alphaId()])
        ->assertSet('perLanguage', false)
        ->assertSee('All languages')
        ->assertDontSeeHtml('wire:model="times.pt.publish"');
});

it('schedules every language from the one row', function (): void {
    $publish = now()->addDay()->format('Y-m-d\TH:i');

    Livewire::test(PageSchedule::class, ['pageId' => alphaId()])
        ->set('all.publish', $publish)
        ->call('saveSchedule')
        ->assertHasNoErrors();

    expect(array_map(fn (array $entry): array => [$entry['language'], $entry['action'], $entry['at']->format('Y-m-d\TH:i')], lynx()->pages->schedules(alphaId())))
        ->toBe([['default', 'publish', $publish], ['pt', 'publish', $publish]]);
});

it('opens on the one row when every language has the same times', function (): void {
    lynx()->pages->schedule(alphaId(), 'default', publishAt: $at = now()->addDay()->startOfMinute());
    lynx()->pages->schedule(alphaId(), 'pt', publishAt: $at);

    Livewire::test(PageSchedule::class, ['pageId' => alphaId()])
        ->assertSet('perLanguage', false)
        ->assertSet('all.publish', $at->format('Y-m-d\TH:i'));
});

it('opens per language when the languages have different times', function (): void {
    lynx()->pages->schedule(alphaId(), 'pt', publishAt: now()->addDay());

    Livewire::test(PageSchedule::class, ['pageId' => alphaId()])->assertSet('perLanguage', true);
});

it('shows why a time on the one row is refused', function (): void {
    Livewire::test(PageSchedule::class, ['pageId' => alphaId()])
        ->set('all.publish', now()->subDay()->format('Y-m-d\TH:i'))
        ->call('saveSchedule')
        ->assertHasErrors('all.publish')
        ->assertSee('must be in the future');

    expect(lynx()->pages->schedules(alphaId()))->toBe([]);
});

it('clears a time left empty', function (): void {
    lynx()->pages->schedule(alphaId(), 'pt', publishAt: now()->addDay());

    Livewire::test(PageSchedule::class, ['pageId' => alphaId()])
        ->assertSet('times.pt.publish', fn (string $value): bool => $value !== '')
        ->set('times.pt.publish', '')
        ->call('saveSchedule');

    expect(lynx()->pages->schedules(alphaId()))->toBe([]);
});

it('shows why a time is refused', function (): void {
    Livewire::test(PageSchedule::class, ['pageId' => alphaId()])
        ->set('perLanguage', true)
        ->set('times.pt.publish', now()->subDay()->format('Y-m-d\TH:i'))
        ->call('saveSchedule')
        ->assertHasErrors('times.pt.publish')
        ->assertSee('must be in the future');
});

it('shows entries that could not run, with the reason', function (): void {
    lynx()->pages->schedule(alphaId(), 'pt', publishAt: now()->addDay());
    DB::table('lynx_schedules')->update(['failed_at' => now(), 'error' => 'Page [alpha] is locked.']);

    Livewire::test(PageSchedule::class, ['pageId' => alphaId()])->assertSee('Could not publish: Page [alpha] is locked.');
});

it('is read only without pages.publish', function (): void {
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view'], ['permission' => 'pages.edit']]]);
    lynx()->run(AssignRoles::class, ['user' => '7', 'roles' => ['writer']]);
    test()->actingAs(new GenericUser(['id' => 7]));

    Livewire::test(PageSchedule::class, ['pageId' => alphaId()])
        ->assertDontSeeHtml('wire:click="saveSchedule"')
        ->assertSee('You may not change when this page is published.');
});

it('carries the one row\'s times into each language when switching to per language', function (): void {
    $publish = now()->addDay()->format('Y-m-d\TH:i');

    Livewire::test(PageSchedule::class, ['pageId' => alphaId()])
        ->set('all.publish', $publish)
        ->set('perLanguage', true)
        ->assertSet('times.pt.publish', $publish)
        ->assertSet('times.default.publish', $publish);
});
