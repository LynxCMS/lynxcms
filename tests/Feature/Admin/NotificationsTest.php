<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Admin\Livewire\PageTree;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    $this->actingAs(new GenericUser(['id' => 1]));
    $this->alphaId = (int) lynx()->pages->get('/courses/alpha/')?->id;
});

it('puts the notification bar at the bottom of every admin screen', function (): void {
    $this->get('/lynx/pages')->assertOk()->assertSee('class="lynx-notices"', false)->assertDontSee('data-slot="sonner-toaster"', false);
});

it('notifies a save in green and nothing to save in yellow', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => test()->alphaId])
        ->call('save')->assertDispatched('lynx-notify', message: 'No changes to save.', type: 'warning')
        ->set('values.title.default', 'Alpha 2')->call('save')->assertDispatched('lynx-notify', message: 'Saved.', type: 'success');
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
