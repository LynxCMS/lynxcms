<?php

declare(strict_types=1);

use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;
use LynxCms\Forms\Actions\CreateForm;
use LynxCms\Forms\Builder\PageForms;
use LynxCms\Forms\FormRegistry;
use LynxCms\Forms\FormSchema;

/*
 * A form is a page without a URL: it shows wherever a template puts it, in every language, and
 * its key (what templates and submissions name it by) is its name in the default language.
 */

beforeEach(function (): void {
    courseSite();
    $this->form = lynx()->run(CreateForm::class, ['name' => 'contact', 'label' => ['default' => 'Contact us']])->valueAs(Page::class);
    lynx()->run(SavePage::class, ['id' => $this->form->id, 'values' => [FormSchema::FIELDS => [['type' => 'text', 'values' => ['form_label' => ['default' => 'Your name']]]]]]);
    lynx()->pages->setStatus($this->form->id, remove: ['unpublished']);
});

function formIn(string $language, string $key): ?string
{
    app()->forgetInstance(FormRegistry::class);
    app()->forgetInstance(PageForms::class);

    return lynx()->language($language, fn () => app(FormRegistry::class)->get($key)?->key());
}

it('shows a form in a language it is not published in', function (): void {
    expect(lynx()->pages->get($this->form->id)?->isActiveIn('pt'))->toBeFalse()
        ->and(formIn('pt', 'contact'))->toBe('contact');
});

it('keeps a form\'s key in every language, even with a name of its own in one', function (): void {
    lynx()->pages->get($this->form->id)?->setLanguageName('pt', 'contacto', active: true)->save();

    expect(formIn('pt', 'contact'))->toBe('contact')
        ->and(formIn('pt', 'contacto'))->toBeNull()
        ->and(FormSchema::key(lynx()->language('pt', fn () => lynx()->pages->get($this->form->id))))->toBe('contact');
});
