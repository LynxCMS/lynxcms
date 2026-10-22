<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Admin\Editor\ChangeReview;
use LynxCms\Admin\Editor\ReviewEntry;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Pages\Page;

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

function reviewPage(): Page
{
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'delivery-8';
    $page->title = 'Delivery';
    $page->price = 249;
    $page->setLanguageValue('pt', 'title', 'Guia');
    $page->save();

    return lynx()->pages->get($page->id);
}

function reviewEditor(Page $page): Testable
{
    return Livewire::test(PageEditor::class, ['pageId' => $page->id]);
}

it('lists each changed field and language with its value before and after', function (): void {
    $page = reviewPage();

    reviewEditor($page)
        ->set('values.title.pt', 'Guia de Entrega')
        ->set('values.price.default', '275')
        ->call('review')
        ->assertHasNoErrors()
        ->assertSet('isReviewing', true)
        ->assertSeeInOrder(['Review changes', 'Title', 'Portugu', 'Guia', 'Guia de Entrega', 'Price', '249', '275', 'Save these changes', 'Keep editing']);

    $stored = lynx()->pages->get($page->id);
    expect($stored->price)->toBe(249)
        ->and($stored->getLanguageValue('pt', 'title'))->toBe('Guia');
});

it('says when there is nothing to review', function (): void {
    reviewEditor(reviewPage())->call('review')->assertSet('isReviewing', false)->assertDispatched('lynx-notify', message: 'No changes to review.');
});

it('shows validation errors instead of the review', function (): void {
    reviewEditor(reviewPage())
        ->set('values.title.default', '')
        ->call('review')
        ->assertHasErrors('values.title')
        ->assertSet('isReviewing', false);
});

it('closes the review when a value changes or on request', function (): void {
    $editor = reviewEditor(reviewPage())->set('values.price.default', '275')->call('review');

    $editor->set('values.price.default', '280')->assertSet('isReviewing', false);
    $editor->call('review')->call('closeReview')->assertSet('isReviewing', false);
});

it('saves the reviewed changes', function (): void {
    $page = reviewPage();

    reviewEditor($page)->set('values.price.default', '275')->call('review')->call('save')->assertSet('isReviewing', false)->assertDispatched('lynx-notify', message: 'Saved.');

    expect(lynx()->pages->get($page->id)->price)->toBe(275);
});

it('builds entries from a pages.save values input, page references as titles', function (): void {
    $page = reviewPage();
    $other = createCourses('alpha')['alpha'];

    $entries = app(ChangeReview::class)->forSave($page, ['title' => ['pt' => 'Guia de Entrega'], 'related' => [$other->id], 'nope' => 1]);

    expect(array_map(fn (ReviewEntry $entry): array => [$entry->label, $entry->language, $entry->type], $entries))
        ->toBe([['Title', 'Português', 'text'], ['Related', null, 'page']])
        ->and($entries[1]->before)->toBe([])
        ->and($entries[1]->after)->toBe([['id' => $other->id, 'title' => 'Alpha']]);
});

it('guards unsaved edits and saves with the keyboard only where Save is offered', function (): void {
    $page = reviewPage();

    reviewEditor($page)->assertSeeHtml('x-data="lynxEditorGuard"')->assertSeeHtml('data-lynx-save');
    reviewEditor($page)->set('tab', 'settings')->assertDontSeeHtml('data-lynx-save');
});

it('previews formatted output under fields that have formatters', function (): void {
    lynx()->fields->update('summary', ['settings' => ['formatters' => ['smartypants']]]);
    $page = reviewPage();

    reviewEditor($page)
        ->assertDontSee('“')
        ->set('values.summary.default', 'A "smart" -- summary')
        ->assertSee('Preview')
        ->assertSeeHtml('A “smart” – summary');
});
