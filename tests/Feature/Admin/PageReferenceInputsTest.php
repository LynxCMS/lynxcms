<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Pages\Page;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta', 'gamma');
    $this->actingAs(new GenericUser(['id' => 1]));
});

/** @param  array<string, mixed>  $settings */
function relatedAs(array $settings): Page
{
    lynx()->fields->update('related', ['settings' => $settings + ['templates' => ['course']]]);

    return lynx()->pages->get('/courses/alpha/');
}

function courseId(string $name): int
{
    return (int) lynx()->pages->get("/courses/{$name}/")->id;
}

it('refuses an unknown input style', function (): void {
    lynx()->fields->update('related', ['settings' => ['input' => 'grid']]);
})->throws(ValidationException::class);

it('keeps the search picker by default', function (): void {
    $page = lynx()->pages->get('/courses/alpha/');

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSeeHtml('lynx-admin.page-picker')
        ->assertDontSeeHtml('type="radio"');
});

it('lists the pages of the allowed templates as radios and saves one', function (): void {
    $page = relatedAs(['input' => 'radios', 'multiple' => false]);

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSeeHtml('type="radio"')
        ->assertSeeHtml('name="values.related.default" value="'.courseId('beta').'"')
        ->assertSee(['Beta', 'Gamma'])
        ->assertDontSeeHtml('name="values.related.default" value="'.lynx()->pages->get('/courses/')->id.'"')
        ->set('values.related.default', (string) courseId('gamma'))
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->pages->get($page->id)->related?->id)->toBe(courseId('gamma'));
});

it('saves several pages with checkboxes', function (): void {
    $page = relatedAs(['input' => 'checkboxes']);

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSeeHtml('type="checkbox"')
        ->set('values.related.default', [(string) courseId('beta'), (string) courseId('gamma')])
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->pages->get($page->id)->related->ids())->toBe([courseId('beta'), courseId('gamma')]);
});

it('offers a select with an empty choice for one page', function (): void {
    $page = relatedAs(['input' => 'select', 'multiple' => false]);
    $page->related = [courseId('beta')];
    $page->save();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSeeHtml('<select')
        ->assertSet('values.related.default', (string) courseId('beta'))
        ->set('values.related.default', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->pages->get($page->id)->related)->toBeNull();
});

it('keeps a chosen page the list no longer offers', function (): void {
    $page = relatedAs(['input' => 'radios', 'multiple' => false]);
    $courses = (int) lynx()->pages->get('/courses/')->id;
    lynx()->fields->update('related', ['settings' => ['templates' => []]]);
    $page->related = [$courses];
    $page->save();
    relatedAs(['input' => 'radios', 'multiple' => false]);

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSeeHtml('name="values.related.default" value="'.$courses.'"')
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->pages->get($page->id)->related?->id)->toBe($courses);
});

it('lists pages for a page field inside a repeater item', function (): void {
    lynx()->fields->add('partner', 'page', settings: ['multiple' => false, 'input' => 'radios', 'templates' => ['course']]);
    lynx()->fields->add('credits', 'repeater', settings: ['fields' => [['name' => 'partner']], 'item_label' => '{partner}']);
    lynx()->templates->update('course', ['add' => ['credits']]);
    $page = lynx()->pages->get('/courses/alpha/');

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->call('addItem', 'credits')
        ->assertSet('values.credits.default.0.values.partner.default', '')
        ->set('values.credits.default.0.values.partner.default', (string) courseId('beta'))
        ->assertSee('Beta')
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->pages->get($page->id)->getUnformatted('credits')[0]['values'])->toBe(['partner' => [courseId('beta')]]);
});
