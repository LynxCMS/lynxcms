<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Core\Actions\Fields\UpdateField;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Forms\Actions\CreateForm;
use LynxCms\Forms\Actions\InstallForms;
use LynxCms\Forms\Builder\PageForm;
use LynxCms\Forms\FormSchema;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
});

/** A block of the form_fields matrix. */
function block(string $type, array $values, int $depth = 0, bool $enabled = true): array
{
    return ['type' => $type, 'depth' => $depth, 'enabled' => $enabled, 'values' => $values];
}

/**
 * Creates a builder form and saves its blocks, published.
 *
 * @param  list<array<string, mixed>>  $blocks
 */
function builderForm(array $blocks, string $name = 'contact', array $values = []): Page
{
    $page = lynx()->run(CreateForm::class, ['name' => $name, 'label' => ['default' => 'Contact us', 'pt' => 'Contacte-nos']])->valueAs(Page::class);
    lynx()->run(SavePage::class, ['id' => $page->id, 'status' => [], 'values' => [FormSchema::FIELDS => $blocks] + $values]);

    return lynx()->pages->get((int) $page->id) ?? throw new RuntimeException('No form page.');
}

/** @return list<array<string, mixed>> */
function contactBlocks(): array
{
    return [
        block('text', ['form_label' => ['default' => 'Your name', 'pt' => 'O seu nome'], 'form_required' => true, 'form_width' => 50]),
        block('email', ['form_label' => ['default' => 'Email'], 'form_name' => 'email', 'form_required' => true, 'form_width' => 50]),
        block('select', ['form_label' => ['default' => 'Subject', 'pt' => 'Assunto'], 'form_name' => 'subject', 'form_options' => [
            ['value' => 'work', 'label' => ['default' => 'A project', 'pt' => 'Um projeto']],
            ['value' => 'press', 'label' => ['default' => 'Press', 'pt' => 'Imprensa']],
        ]]),
        block('textarea', ['form_label' => ['default' => 'Outlet'], 'form_name' => 'outlet', 'form_show_if' => 'subject', 'form_show_if_values' => 'press']),
        block('step', ['form_label' => ['default' => 'Details', 'pt' => 'Detalhes']]),
        block('group', ['form_label' => ['default' => 'People'], 'form_name' => 'people', 'form_max_rows' => 3]),
        block('text', ['form_label' => ['default' => 'Person'], 'form_name' => 'person', 'form_required' => true], depth: 1),
        block('file', ['form_label' => ['default' => 'Attachment'], 'form_name' => 'attachment', 'form_extensions' => 'pdf, jpg', 'form_max_kb' => 2048, 'form_max_files' => 2]),
        block('checkbox', ['form_label' => ['default' => 'Hidden'], 'form_name' => 'hidden_one'], enabled: false),
    ];
}

it('installs its schema the first time a form is created', function (): void {
    expect(lynx()->templates->find(FormSchema::FORM_TEMPLATE))->toBeNull();

    builderForm(contactBlocks());

    expect(lynx()->templates->find(FormSchema::FORM_TEMPLATE))->not->toBeNull()
        ->and(lynx()->fields->find(FormSchema::FIELDS)?->type)->toBe('matrix')
        ->and(lynx()->fields->find(FormSchema::PICKER)?->type)->toBe('page')
        ->and(lynx()->pages->findOne('template='.FormSchema::ROOT_TEMPLATE.', include=all')?->hasStatus(PageStatus::SYSTEM))->toBeTrue();
});

it('turns a form page into a definition with steps, groups, conditions and files', function (): void {
    builderForm(contactBlocks());
    $form = lynx()->forms()->get('contact');

    expect($form)->toBeInstanceOf(PageForm::class)
        ->and(array_map(fn ($field) => $field->name, $form->fields()))->toBe(['your_name', 'email', 'subject', 'outlet', 'people', 'attachment'])
        ->and(array_map(fn ($step) => $step->fields, $form->steps()))->toBe([['your_name', 'email', 'subject', 'outlet'], ['people', 'attachment']])
        ->and($form->field('people')?->fields[0]->name)->toBe('person')
        ->and($form->field('outlet')?->showIf)->toBe(['field' => 'subject', 'operator' => 'in', 'values' => ['press']])
        ->and($form->field('attachment')?->extensions)->toBe(['pdf', 'jpg'])
        ->and($form->field('your_name')?->width)->toBe(50)
        ->and(lynx()->language('pt', fn () => $form->field('subject')?->options['press']->in()))->toBe('Imprensa')
        ->and(lynx()->language('pt', fn () => $form->text('label')->in()))->toBe('Contacte-nos')
        ->and(lynx()->language('pt', fn () => $form->field('email')?->label->in()))->toBe('Email');
});

it('refuses blocks that would make a broken form, on the block that is wrong', function (array $blocks, string $key): void {
    try {
        builderForm($blocks);
        $this->fail('The form saved.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($key);
    }
})->with([
    'repeated name' => [[block('text', ['form_label' => 'A', 'form_name' => 'a']), block('text', ['form_label' => 'B', 'form_name' => 'a'])], 'values.form_fields.1.form_name'],
    'choices without options' => [[block('radio', ['form_label' => 'Pick'])], 'values.form_fields.0.form_options'],
    'unknown condition' => [[block('text', ['form_label' => 'A', 'form_show_if' => 'nope', 'form_show_if_values' => 'x'])], 'values.form_fields.0.form_show_if'],
    'indented without a group' => [[block('text', ['form_label' => 'A'], depth: 1)], 'values.form_fields.0.type'],
    'a step inside a group' => [[block('group', ['form_label' => 'G']), block('step', ['form_label' => 'S'], depth: 1)], 'values.form_fields.1.type'],
    'no fields' => [[block('step', ['form_label' => 'S'])], 'values.form_fields'],
    'a comparison without a number' => [[block('number', ['form_label' => 'N', 'form_name' => 'n']), block('text', ['form_label' => 'A', 'form_show_if' => 'n', 'form_show_if_values' => '>= lots'])], 'values.form_fields.1.form_show_if_values'],
]);

it('reads comparisons and yes or no from a block\'s condition', function (string $values, array $showIf): void {
    builderForm([
        block('number', ['form_label' => 'Guests', 'form_name' => 'guests']),
        block('checkbox', ['form_label' => 'Catering', 'form_name' => 'catering']),
        block('text', ['form_label' => 'Notes', 'form_name' => 'notes', 'form_show_if' => $showIf['field'], 'form_show_if_values' => $values]),
    ]);

    expect(lynx()->forms()->get('contact')?->field('notes')?->showIf)->toBe($showIf);
})->with([
    'at least' => ['>= 50', ['field' => 'guests', 'operator' => '>=', 'values' => ['50']]],
    'not equal' => ['!= 3', ['field' => 'guests', 'operator' => 'not_in', 'values' => ['3']]],
    'ticked' => ['yes', ['field' => 'catering', 'operator' => 'in', 'values' => ['1']]],
    'not ticked' => ['No', ['field' => 'catering', 'operator' => 'in', 'values' => ['0']]],
]);

it('refuses a form named like a code form', function (): void {
    lynx()->forms()->register(QuoteForm::class);

    expect(fn (): ActionResult => lynx()->run(CreateForm::class, ['name' => 'quote', 'label' => 'Quote']))->toThrow(ValidationException::class, 'quote');
});

it('serves only published forms, and code forms first', function (): void {
    lynx()->run(CreateForm::class, ['name' => 'draft-form', 'label' => 'Draft']);
    expect(lynx()->forms()->get('draft-form'))->toBeNull();

    builderForm(contactBlocks());
    expect(array_keys(lynx()->forms()->all()))->toContain('contact');
});

it('writes nothing on a dry run and rolls back with its schema', function (): void {
    lynx()->run(CreateForm::class, ['name' => 'contact', 'label' => 'Contact'], dryRun: true);
    expect(lynx()->templates->find(FormSchema::FORM_TEMPLATE))->toBeNull();

    lynx()->hook(CreateForm::class)->after(fn () => throw new ActionHalted('No.'));
    expect(fn (): ActionResult => lynx()->run(CreateForm::class, ['name' => 'contact', 'label' => 'Contact']))->toThrow(ActionHalted::class)
        ->and(lynx()->templates->find(FormSchema::FORM_TEMPLATE))->toBeNull();
});

it('refuses form names that are not slugs', function (): void {
    lynx()->run(CreateForm::class, ['name' => 'Contact Us', 'label' => 'Contact']);
})->throws(ValidationException::class);

it('renders a picked form with lynx-form', function (): void {
    $form = builderForm(contactBlocks());

    expect(Blade::render('<x-lynx-form :form="$form" />', ['form' => $form]))->toContain('wire:name="lynx-form"')->toContain('Your name')
        ->and(Blade::render('<x-lynx-form form="contact" />'))->toContain('Your name')
        ->and(Blade::render('<x-lynx-form :form="null" />'))->toBe('');
});

it('brings the builder\'s own field labels up to date', function (): void {
    builderForm(contactBlocks());
    lynx()->run(UpdateField::class, ['field' => FormSchema::SHOW_IF_VALUES, 'label' => 'Old wording']);

    lynx()->run(InstallForms::class);

    expect(lynx()->fields->find(FormSchema::SHOW_IF_VALUES)?->label)->toBe(FormSchema::fields()[FormSchema::SHOW_IF_VALUES][2]);
});
