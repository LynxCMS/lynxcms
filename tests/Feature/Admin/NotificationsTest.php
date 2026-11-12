<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageChildren;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Admin\Livewire\PageSettings;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Core\Pages\PageStatus;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    $this->actingAs(new GenericUser(['id' => 1]));
    $this->alphaId = (int) lynx()->pages->get('/courses/alpha/')?->id;
});

it('puts the notification bar at the bottom of every admin screen', function (): void {
    $this->get('/lynx/pages')->assertOk()->assertSee('class="lynx-notices"', false)->assertDontSee('data-slot="sonner-toaster"', false)
        ->assertDontSee('data-flashed-notice', false);
});

it('notifies a save in green and nothing to save in yellow', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => test()->alphaId])
        ->call('save')->assertDispatched('lynx-notify', type: 'warning')
        ->set('values.title.default', 'Alpha 2')->call('save')->assertDispatched('lynx-notify', type: 'success');
});

it('notifies a refused save in red and keeps the errors by the inputs', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => test()->alphaId])
        ->set('values.title.default', '')->call('save')
        ->assertHasErrors('values.title')
        ->assertDispatched('lynx-notify', fn (string $name, array $params): bool => $params['type'] === 'error' && str_starts_with($params['message'], 'Not saved:'));
});

it('places a row\'s actions right after its title', function (): void {
    $html = Livewire::test(PageTree::class)->html();

    expect($html)->toContain('lynx-tree-actions flex')->not->toContain('ms-auto flex flex-wrap items-center gap-0.5');
});

it('shows a notice kept across a redirect on the next admin screen', function (): void {
    session()->flash('lynx-notify', ['message' => 'Kept', 'type' => 'success']);

    $this->get('/lynx/pages')->assertOk()->assertSee('data-flashed-notice="success"', false);
});

it('notifies a status change from a tree row', function (): void {
    Livewire::test(PageTree::class)
        ->call('toggleStatus', test()->alphaId, 'hidden')
        ->assertDispatched('lynx-notify', type: 'success');

    expect(lynx()->pages->get(test()->alphaId)->hasStatus(PageStatus::HIDDEN))->toBeTrue();
});

it('notifies a status change from the Children tab', function (): void {
    Livewire::test(PageChildren::class, ['pageId' => (int) lynx()->pages->get('/courses/')?->id])
        ->call('toggle', test()->alphaId, 'unpublished')
        ->assertDispatched('lynx-notify', type: 'success');
});

it('says the new status in a status notice, without the page title', function (): void {
    Livewire::test(PageSettings::class, ['pageId' => test()->alphaId])
        ->call('toggleStatus', 'unpublished')
        ->assertDispatched('lynx-notify', fn (string $name, array $params): bool => $params['message'] === __('lynx-admin::pages.status_changed.unpublished.on') && ! str_contains($params['message'], 'Alpha'));
});
