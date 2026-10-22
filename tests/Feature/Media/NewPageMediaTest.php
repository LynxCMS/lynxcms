<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageCreator;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Media\Actions\StoreMedia;

beforeEach(function (): void {
    Storage::fake('public');
    courseSite();
    lynx()->fields->add('poster', 'images', settings: ['max_files' => 1]);
    lynx()->templates->add('event', fields: [['name' => 'title', 'required' => true], ['name' => 'poster', 'required' => true]]);
});

/** Creates an event under the home page through pages.save. */
function createEvent(array $status): Page
{
    return lynx()->run(SavePage::class, ['template' => 'event', 'parent' => '/', 'values' => ['title' => 'Gig'], 'status' => $status])->valueAs(Page::class);
}

it('creates an unpublished page before its required images exist', function (): void {
    $page = createEvent(['unpublished']);

    expect($page->id)->toBeInt()->and(lynx()->pages->get('/gig/'))->not->toBeNull();
});

it('refuses a published page without its required images, saying how to add them', function (): void {
    expect(fn (): Page => createEvent([]))->toThrow(ValidationException::class, 'Create the page unpublished');
});

it('requires the images on every later save', function (): void {
    $page = createEvent(['unpublished']);

    expect(fn (): ActionResult => lynx()->run(SavePage::class, ['id' => $page->id, 'values' => ['title' => 'Gig night']]))
        ->toThrow(ValidationException::class, 'is required');

    $entry = lynx()->run(StoreMedia::class, ['page' => $page->id, 'field' => 'poster', 'base64' => base64_encode(jpeg(200, 100)), 'filename' => 'poster.jpg'])->value;
    lynx()->run(SavePage::class, ['id' => $page->id, 'values' => ['title' => 'Gig night', 'poster' => [['values' => $entry]]]]);

    expect(lynx()->pages->get('/gig/')?->poster?->file())->toBe($entry['file']);
});

it('leaves required images out of the new page form and creates the page unpublished', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    $creator = Livewire::test(PageCreator::class, ['parentId' => lynx()->pages->get('/')->id])
        ->set('template', 'event')
        ->assertDontSeeHtml('id="field-poster"')
        ->assertSee('Add Poster once the page exists')
        ->assertDontSeeHtml('wire:model="publish"');

    $creator->set('values.title.default', 'Gig')->set('publish', true)->call('create')->assertHasNoErrors();

    expect(lynx()->pages->get('/gig/')?->status & PageStatus::UNPUBLISHED)->toBe(PageStatus::UNPUBLISHED);
});
