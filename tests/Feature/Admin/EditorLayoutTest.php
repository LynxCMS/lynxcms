<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('seo_title', 'text', translatable: true);
    lynx()->fields->add('author', 'text');
    lynx()->templates->update('course', ['add' => ['seo_title', 'author']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $alpha->seo_title = 'Alpha for search';
    $alpha->save();
    $this->alphaId = (int) $alpha->id;
    $this->actingAs(new GenericUser(['id' => 1]));
});

function layoutEditor(): Testable
{
    lynx()->templates->update('course', [
        'tabs' => [['key' => 'seo', 'label' => 'SEO']],
        'fields' => [
            ['name' => 'title', 'required' => true],
            ['section' => 'intro', 'label' => 'Introduction', 'collapsed' => true, 'fields' => ['summary', 'body']],
            ['name' => 'author', 'column' => 'sidebar'],
            'price',
            ['section' => 'meta', 'label' => 'Search engines', 'tab' => 'seo', 'fields' => [['name' => 'seo_title', 'required' => true]]],
            'related',
        ],
    ]);

    return Livewire::test(PageEditor::class, ['pageId' => test()->alphaId]);
}

it('shows the template tabs, sections and sidebar', function (): void {
    layoutEditor()
        ->assertSeeInOrder(['Content', 'SEO', 'Settings'])
        ->assertSeeHtml('data-section="intro"')
        ->assertSeeHtml('data-collapsed="true"')
        ->assertSee('Introduction')
        ->assertSeeHtml('aria-label="Sidebar"')
        ->assertDontSeeHtml('@xl:grid-cols-[12rem_minmax(0,1fr)]') // a sidebar puts every label above its input
        ->assertSeeHtml('id="field-author"')
        ->assertDontSeeHtml('id="field-seo_title"')
        ->set('tab', 'seo')
        ->assertSee('Search engines')
        ->assertSeeHtml('id="field-seo_title"')
        ->assertDontSeeHtml('id="field-title"');
});

it('saves fields of every tab, and opens the tab of a field with an error', function (): void {
    layoutEditor()
        ->set('values.title.default', 'Alpha, renamed')
        ->call('save')
        ->assertHasNoErrors()
        ->set('values.seo_title.default', '')
        ->call('save')
        ->assertHasErrors()
        ->assertSet('tab', 'seo');

    expect((string) lynx()->pages->get($this->alphaId)?->title)->toBe('Alpha, renamed');
});

it('keeps the single list for templates without a layout', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => $this->alphaId])
        ->assertDontSeeHtml('aria-label="Sidebar"')
        ->assertDontSeeHtml('data-section=')
        ->assertSeeHtml('@xl:grid-cols-[12rem_minmax(0,1fr)]')
        ->assertSeeHtml('aria-label="Content"');
});
