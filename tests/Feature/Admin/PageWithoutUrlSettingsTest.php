<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Admin\AdminBranches;
use LynxCms\Admin\Livewire\PageHistory;
use LynxCms\Admin\Livewire\PageSchedule;
use LynxCms\Admin\Livewire\PageSettings;
use LynxCms\Core\Pages\Page;
use LynxCms\Forms\Actions\CreateForm;

/*
 * The Settings tab of a page without a URL, such as a form or a block of site settings: it shows
 * wherever a template puts it, in every language, so it has one name for templates, no
 * languages to publish in, no Hidden, one schedule and no old URLs. Pages a module manages
 * (forms under /_forms/) show no template or parent, which can't change there.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    lynx()->templates->add('snippet', fields: [['name' => 'title', 'required' => true]], hasUrl: false);
    $snippet = lynx()->pages->new('snippet', '/');
    $snippet->name = 'footer-note';
    $snippet->title = 'Footer note';
    $snippet->save();
    $this->snippet = $snippet;
    $this->form = lynx()->run(CreateForm::class, ['name' => 'contact', 'label' => ['default' => 'Contact us']])->valueAs(Page::class);
});

it('names a page without a URL once, for templates', function (): void {
    Livewire::test(PageSettings::class, ['pageId' => $this->snippet->id])
        ->assertSeeHtml('data-template-name')
        ->assertDontSeeHtml('wire:model.live="active.pt"');

    Livewire::test(PageSettings::class, ['pageId' => lynx()->pages->get('/courses/')?->id])
        ->assertDontSeeHtml('data-template-name')
        ->assertSeeHtml('wire:model.live="active.pt"');
});

it('keeps the other languages\' names when the name changes', function (): void {
    $this->snippet->setLanguageName('pt', 'nota')->save();

    Livewire::test(PageSettings::class, ['pageId' => $this->snippet->id])
        ->set('names.default', 'footer-text')
        ->call('saveNames')
        ->assertHasNoErrors();

    $saved = lynx()->pages->get($this->snippet->id);

    expect($saved?->name('default'))->toBe('footer-text')->and($saved?->name('pt'))->toBe('nota');
});

it('leaves Hidden out for a page without a URL', function (): void {
    Livewire::test(PageSettings::class, ['pageId' => $this->snippet->id])
        ->assertDontSeeHtml("toggleStatus('hidden')")
        ->assertSeeHtml("toggleStatus('unpublished')");
});

it('schedules a page without a URL as a whole', function (): void {
    Livewire::test(PageSchedule::class, ['pageId' => $this->snippet->id])
        ->assertDontSeeHtml('wire:model.live="perLanguage"')
        ->set('all.unpublish', now()->addDay()->format('Y-m-d\TH:i'))
        ->call('saveSchedule')
        ->assertHasNoErrors();

    expect(array_column(lynx()->pages->schedules($this->snippet->id), 'language'))->toBe(['default']);
});

it('hides the template and parent of pages a module manages', function (): void {
    expect(app(AdminBranches::class)->contains($this->form))->toBeTrue()
        ->and(app(AdminBranches::class)->contains($this->snippet))->toBeFalse();

    Livewire::test(PageSettings::class, ['pageId' => $this->form->id])
        ->assertDontSeeHtml('aria-labelledby="settings-template"')
        ->assertDontSeeHtml('aria-labelledby="settings-parent"');
    Livewire::test(PageSettings::class, ['pageId' => $this->snippet->id])
        ->assertSeeHtml('aria-labelledby="settings-template"');
});

it('leaves old URLs out of a page without one', function (): void {
    Livewire::test(PageHistory::class, ['pageId' => $this->snippet->id])
        ->assertDontSeeHtml('aria-labelledby="settings-redirects"')
        ->assertSeeHtml('aria-labelledby="settings-backlinks"');
});

it('leaves Unique out of a page without a URL, unless it is set', function (): void {
    Livewire::test(PageSettings::class, ['pageId' => $this->snippet->id])->assertDontSeeHtml("toggleStatus('unique')");

    lynx()->pages->setStatus($this->snippet->id, add: ['unique']);

    Livewire::test(PageSettings::class, ['pageId' => $this->snippet->id])->assertSeeHtml("toggleStatus('unique')");
});

it('names a unique page without a URL by its title when its name is refused', function (): void {
    lynx()->pages->setStatus($this->snippet->id, add: ['unique']);
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'footer-note';
    $page->title = 'Footer note';

    expect(fn () => $page->save())->toThrow(ValidationException::class, '“Footer note”');
});
