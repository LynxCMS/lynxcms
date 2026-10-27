<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Editor\ActivityDescription;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Activity\ActivityEvent;
use LynxCms\Core\Pages\Page;

/*
 * What an activity event did, in parts the admin words for editors (the activity-summary
 * component): the kind of change, the fields and languages it touched, where a page moved.
 * Actions it doesn't know keep their own summary.
 */

beforeEach(function (): void {
    courseSite();
    $this->alpha = createCourses('alpha')['alpha'];
});

function lastEventOf(Page $page): ActivityEvent
{
    return ActivityEvent::query()->where('subject_type', 'page')->where('subject_id', (string) $page->id)->latest('id')->firstOrFail();
}

it('says a page was created', function (): void {
    expect((new ActivityDescription)->of(lastEventOf($this->alpha))['kind'])->toBe('created');
});

it('names the fields a save changed, with their languages', function (): void {
    $this->alpha->setLanguageValue('pt', 'title', 'Alfa');
    $this->alpha->summary = 'New summary';
    $this->alpha->save();

    $description = (new ActivityDescription)->of(lastEventOf($this->alpha));

    expect($description['kind'])->toBe('changed')
        ->and(array_column($description['fields'], 'name'))->toBe(['title', 'summary'])
        ->and(array_column($description['fields'][0]['languages'], 'code'))->toBe(['pt'])
        ->and($description['more'])->toBe(0);
});

it('counts the fields past the first three', function (): void {
    $this->alpha->title = 'Alpha 2';
    $this->alpha->summary = 'Summary';
    $this->alpha->body = '<p>Body</p>';
    $this->alpha->price = 10;
    $this->alpha->save();

    $description = (new ActivityDescription)->of(lastEventOf($this->alpha));

    expect($description['fields'])->toHaveCount(3)->and($description['more'])->toBe(1);
});

it('leaves languages out on a site with one', function (): void {
    lynx()->languages->delete('pt');
    $this->alpha->title = 'Alpha 2';
    $this->alpha->save();

    expect((new ActivityDescription)->of(lastEventOf($this->alpha))['fields'][0]['languages'])->toBe([]);
});

it('turns a status change into what happened to the page', function (): void {
    lynx()->pages->setStatus($this->alpha, add: ['unpublished', 'locked']);

    $description = (new ActivityDescription)->of(lastEventOf($this->alpha));

    expect($description['kind'])->toBe('status')->and($description['statuses'])->toBe(['unpublished', 'locked']);
});

it('names the page a move went under', function (): void {
    $other = createCourses('other')['other'];
    lynx()->pages->move($this->alpha, $other);

    $description = (new ActivityDescription)->of(lastEventOf($this->alpha));

    expect($description['kind'])->toBe('moved')->and($description['target'])->toBe('Other');
});

it('words a module action that saves a page by what the save did', function (): void {
    $created = new ActivityEvent(['action' => 'menus.create', 'changes' => [
        ['type' => 'created', 'subject' => 'page', 'id' => 9, 'summary' => 'Create menu page footer: title [default]', 'data' => ['template' => 'menu', 'fields' => ['title [default]']]],
    ]]);
    $changed = new ActivityEvent(['action' => 'menus.save_item', 'changes' => [
        ['type' => 'updated', 'subject' => 'page', 'id' => 9, 'summary' => 'Update page item-1: title [pt]', 'data' => ['fields' => ['title [pt]']]],
    ]]);

    expect((new ActivityDescription)->of($created)['kind'])->toBe('created')
        ->and((new ActivityDescription)->of($changed))->toMatchArray(['kind' => 'changed', 'summary' => ''])
        ->and((new ActivityDescription)->of($changed)['fields'][0]['name'])->toBe('title');
});

it('words a scheduled change by what it did to the page', function (): void {
    $event = new ActivityEvent(['action' => 'pages.run_schedules', 'changes' => [
        ['type' => 'updated', 'subject' => 'page', 'id' => 9, 'summary' => 'Set status of page alpha to []', 'data' => ['from' => ['unpublished'], 'to' => []]],
    ]]);

    expect((new ActivityDescription)->of($event))->toMatchArray(['kind' => 'status', 'statuses' => ['published']]);
});

it('keeps the summary of actions it does not know', function (): void {
    $event = new ActivityEvent(['action' => 'menus.save', 'changes' => [['summary' => 'Save menu item 4'], ['summary' => 'Path']]]);

    expect((new ActivityDescription)->of($event))->toMatchArray(['kind' => 'other', 'summary' => 'Save menu item 4', 'more' => 1]);
});

it('words every kind in the editor\'s panel', function (): void {
    Livewire::actingAs(new GenericUser(['id' => 1]))
        ->test(PageEditor::class, ['pageId' => $this->alpha->id])
        ->assertSeeHtml('data-activity-kind="created"');
});
