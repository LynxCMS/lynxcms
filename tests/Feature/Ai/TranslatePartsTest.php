<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use LynxCms\Contracts\Translation\TranslationProvider;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Strings\PageStrings;
use LynxCms\Forms\Actions\CreateForm;
use LynxCms\Forms\FormSchema;
use LynxCms\Media\Actions\StoreMedia;
use LynxCms\Tests\Fixtures\FakeTranslationProvider;

/*
 * Machine translation reaches the texts that live inside other values (ADR-0105): a picture's
 * description, a form question's options, and a page's own template texts. Only missing texts
 * are filled: these keep no origin, so a person's text is never replaced.
 */

beforeEach(function (): void {
    courseSite();
    $this->provider = new FakeTranslationProvider;
    app()->instance(TranslationProvider::class, $this->provider);
});

it('translates a picture\'s description', function (): void {
    Storage::fake('public');
    lynx()->fields->add('gallery', 'images');
    lynx()->templates->update('course', ['add' => ['gallery']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $entry = lynx()->run(StoreMedia::class, ['page' => $alpha->id, 'field' => 'gallery', 'base64' => base64_encode(jpeg(30, 20)), 'filename' => 'a.jpg'])->value;
    lynx()->run(SavePage::class, ['id' => $alpha->id, 'values' => ['gallery' => [['values' => $entry + ['description' => ['default' => 'A view']]]]]]);

    lynx()->pages->translate($alpha->id, 'pt');

    expect(lynx()->pages->get($alpha->id)?->getUnformatted('gallery')[0]['values']['description'])->toBe(['default' => 'A view', 'pt' => '[pt-PT] A view']);
});

it('translates a form question\'s option labels, leaving labels a person wrote', function (): void {
    $form = lynx()->run(CreateForm::class, ['name' => 'survey', 'label' => ['default' => 'Survey']])->valueAs(Page::class);
    lynx()->run(SavePage::class, ['id' => $form->id, 'values' => [FormSchema::FIELDS => [
        ['type' => 'radio', 'values' => ['form_label' => ['default' => 'Format', 'pt' => 'Formato'], 'form_options' => [
            ['value' => 'film', 'label' => ['default' => 'Film']],
            ['value' => 'series', 'label' => ['default' => 'Series', 'pt' => 'Série']],
        ]]],
    ]]]);

    lynx()->pages->translate($form->id, 'pt');

    $options = lynx()->pages->get($form->id)?->getUnformatted(FormSchema::FIELDS)[0]['values']['form_options'];
    expect(array_column($options, 'label', 'value'))->toBe([
        'film' => ['default' => 'Film', 'pt' => '[pt-PT] Film'],
        'series' => ['default' => 'Series', 'pt' => 'Série'],
    ]);
});

it('translates a page\'s own template texts into its draft, keeping their placeholders', function (): void {
    View::addLocation(__DIR__.'/../../Fixtures/views');
    lynx()->templates->add('landing', fields: ['title']);
    $page = lynx()->pages->new('landing', '/');
    $page->name = 'landing';
    $page->title = 'Landing';
    $page->save();

    lynx()->pages->translate($page->id, 'pt', draft: true);
    $edits = app(PageStrings::class)->pageEdits(lynx()->pages->draft($page->id) ?? $page);

    // hero.title has Portuguese in code already; footer.note is a site text, not the page's.
    expect($edits['pt'] ?? [])->toHaveCount(2)->toMatchArray(['cta.label' => '[pt-PT] Contact <us>', 'hero.intro' => '[pt-PT] We tell stories with **images**.'])
        ->and(app(PageStrings::class)->pageEdits($page))->toBe([]);
});

it('lists the site texts with no text in a language yet', function (): void {
    View::addLocation(__DIR__.'/../../Fixtures/views');
    lynx()->templates->add('landing', fields: ['title']);

    $texts = app(PageStrings::class)->untranslated(lynx()->pages->get('/'), lynx()->languages->get('default'), lynx()->languages->get('pt'), site: true);

    expect($texts)->toBe(['footer.note' => ['text' => 'All rights reserved', 'format' => 'plain', 'label' => 'Footer note']]);
});

it('leaves a template text whose translation lost a placeholder', function (): void {
    View::addLocation(__DIR__.'/../../Fixtures/views');
    lynx()->templates->add('greeting', fields: ['title']);
    $page = lynx()->pages->new('greeting', '/');
    $page->name = 'hello';
    $page->title = 'Hello';
    $page->save();
    app()->instance(TranslationProvider::class, new class implements TranslationProvider
    {
        public function name(): string
        {
            return 'careless';
        }

        public function translate(array $texts, string $from, string $to, array $formats = [], array $options = []): array
        {
            return array_map(fn (string $text): string => 'Olá', $texts);
        }
    });

    lynx()->pages->translate($page->id, 'pt');

    expect(app(PageStrings::class)->pageEdits(lynx()->pages->get($page->id)))->toBe([]);
});
