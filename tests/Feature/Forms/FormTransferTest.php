<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Forms\Actions\CreateForm;
use LynxCms\Forms\Actions\ImportForm;
use LynxCms\Forms\Admin\Livewire\FormList;
use LynxCms\Forms\FormSchema;
use LynxCms\Forms\Transfer\FormExporter;
use LynxCms\Forms\Transfer\FormTransferError;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;

/*
 * A builder form's structure as JSON (ADR-0146): its title, messages and fields, exported from one
 * site and imported into another as a new, unpublished form. Recipients, the one answer per
 * account setting and submissions stay behind.
 */

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
});

/** @return list<array<string, mixed>> */
function transferBlocks(): array
{
    return [
        ['type' => 'text', 'depth' => 0, 'enabled' => true, 'values' => ['form_label' => ['default' => 'Your name', 'pt' => 'O seu nome'], 'form_required' => true, 'form_width' => 50]],
        ['type' => 'select', 'depth' => 0, 'enabled' => true, 'values' => ['form_label' => ['default' => 'Subject', 'pt' => 'Assunto'], 'form_name' => 'subject', 'form_options' => [
            ['value' => 'work', 'label' => ['default' => 'A project', 'pt' => 'Um projeto']],
            ['value' => 'press', 'label' => ['default' => 'Press', 'pt' => 'Imprensa']],
        ]]],
        ['type' => 'textarea', 'depth' => 0, 'enabled' => true, 'values' => ['form_label' => ['default' => 'Outlet'], 'form_name' => 'outlet', 'form_show_if' => 'subject', 'form_show_if_values' => 'press']],
        ['type' => 'step', 'depth' => 0, 'enabled' => true, 'values' => ['form_label' => ['default' => 'Details', 'pt' => 'Detalhes']]],
        ['type' => 'group', 'depth' => 0, 'enabled' => true, 'values' => ['form_label' => ['default' => 'People'], 'form_name' => 'people', 'form_max_rows' => 3]],
        ['type' => 'text', 'depth' => 1, 'enabled' => true, 'values' => ['form_label' => ['default' => 'Person'], 'form_name' => 'person']],
        ['type' => 'checkbox', 'depth' => 0, 'enabled' => false, 'values' => ['form_label' => ['default' => 'Hidden'], 'form_name' => 'hidden_one']],
    ];
}

function transferForm(string $name = 'contact'): Page
{
    $page = lynx()->run(CreateForm::class, ['name' => $name, 'label' => ['default' => 'Contact us', 'pt' => 'Contacte-nos']])->valueAs(Page::class);
    lynx()->run(SavePage::class, ['id' => $page->id, 'status' => [], 'values' => [
        FormSchema::FIELDS => transferBlocks(),
        FormSchema::RECIPIENTS => 'office@example.com',
        FormSchema::ONCE_PER_USER => true,
        FormSchema::SUCCESS => ['default' => 'Thanks.', 'pt' => 'Obrigado.'],
    ]]);

    return lynx()->pages->get((int) $page->id) ?? throw new RuntimeException('No form page.');
}

/** @return array<string, mixed> */
function exported(string $name = 'contact'): array
{
    return app(FormExporter::class)->export($name);
}

it('exports the title, messages and fields, with the language they are keyed by', function (): void {
    transferForm();

    expect(exported())->toMatchArray([
        'lynx_form' => 1,
        'name' => 'contact',
        'default_language' => 'default',
        'title' => ['default' => 'Contact us', 'pt' => 'Contacte-nos'],
        'messages' => ['success' => ['default' => 'Thanks.', 'pt' => 'Obrigado.'], 'error' => null, 'auto_reply' => null],
    ])->and(exported()['fields'])->toEqualCanonicalizing(transferBlocks());
});

it('leaves the recipients, the one answer per account setting and submissions out', function (): void {
    transferForm();

    expect(json_encode(exported(), JSON_THROW_ON_ERROR))->not->toContain('office@example.com')
        ->and(array_keys(exported()))->toBe(['lynx_form', 'name', 'default_language', 'title', 'messages', 'fields']);
});

it('refuses to export a form defined in code, or one the site lacks', function (string $name): void {
    lynx()->forms()->register(QuoteForm::class);

    exported($name);
})->with(['quote', 'missing'])->throws(FormTransferError::class);

it('imports a form as a new, unpublished form without recipients', function (): void {
    transferForm();
    $file = exported();

    $result = lynx()->run(ImportForm::class, ['form' => $file, 'name' => 'contact-copy'])->value;
    $page = lynx()->pages->findOne('template=form, name=contact-copy, include=all');

    expect($result)->toMatchArray(['name' => 'contact-copy', 'dropped_languages' => []])
        ->and($page?->hasStatus(PageStatus::UNPUBLISHED))->toBeTrue()
        ->and($page?->getUnformatted(FormSchema::RECIPIENTS))->toBeNull()
        ->and((bool) $page?->getUnformatted(FormSchema::ONCE_PER_USER))->toBeFalse()
        ->and($page?->getUnformatted('title', 'pt'))->toBe('Contacte-nos')
        ->and(exported('contact-copy'))->toMatchArray([...$file, 'name' => 'contact-copy']);
});

it('refuses a name the site has, and takes another', function (): void {
    transferForm();

    expect(fn (): ActionResult => lynx()->run(ImportForm::class, ['form' => exported()]))->toThrow(ValidationException::class);

    lynx()->run(ImportForm::class, ['form' => exported(), 'name' => 'contact-2']);

    expect(lynx()->pages->findOne('template=form, name=contact-2, include=all'))->not->toBeNull();
});

it('keys the source\'s default language texts by this site\'s, and drops languages this site lacks', function (): void {
    transferForm();
    $file = exported();
    $file['default_language'] = 'en';
    $file['title'] = ['en' => 'Contact us', 'pt' => 'Contacte-nos', 'fr' => 'Contactez-nous'];
    $file['fields'][0]['values']['form_label'] = ['en' => 'Your name', 'fr' => 'Votre nom'];
    $file['fields'][1]['values']['form_options'][0]['label'] = ['en' => 'A project', 'fr' => 'Un projet'];

    $result = lynx()->run(ImportForm::class, ['form' => $file, 'name' => 'imported'])->value;
    $copy = exported('imported');

    expect($result['dropped_languages'])->toBe(['fr'])
        ->and($copy['title'])->toBe(['default' => 'Contact us', 'pt' => 'Contacte-nos'])
        ->and($copy['fields'][0]['values']['form_label'])->toBe(['default' => 'Your name'])
        ->and($copy['fields'][1]['values']['form_options'][0]['label'])->toBe(['default' => 'A project']);
});

it('changes nothing on a dry run', function (): void {
    transferForm();

    lynx()->run(ImportForm::class, ['form' => exported(), 'name' => 'dry'], dryRun: true);

    expect(lynx()->pages->findOne('template=form, name=dry, include=all'))->toBeNull();
});

it('refuses a file that is not a form export', function (array $change): void {
    transferForm();

    lynx()->run(ImportForm::class, ['form' => array_replace_recursive(exported(), $change), 'name' => 'broken']);
})->with([
    'another format' => [['lynx_form' => 2]],
    'an unknown block type' => [['fields' => [0 => ['type' => 'slider']]]],
    'a value the block type lacks' => [['fields' => [3 => ['values' => ['form_options' => []]]]]],
    'a depth below a group' => [['fields' => [5 => ['depth' => 2]]]],
    'a condition on a field the form lacks' => [['fields' => [2 => ['values' => ['form_show_if' => 'topic']]]]],
])->throws(ValidationException::class);

it('needs forms.manage', function (): void {
    transferForm();

    expect(ImportForm::permission())->toBe('forms.manage');
});

it('exports to a file and imports from it on the command line', function (): void {
    transferForm();
    $path = testingPath('form-transfer').'/contact.form.json';
    File::deleteDirectory(dirname($path));

    $this->artisan('lynx:forms:export', ['form' => 'contact', 'path' => $path])->assertSuccessful();
    $this->artisan('lynx:forms:import', ['path' => $path, '--name' => 'from-file', '--dry-run' => true])->assertSuccessful();

    expect(lynx()->pages->findOne('template=form, name=from-file, include=all'))->toBeNull();

    $this->artisan('lynx:forms:import', ['path' => $path, '--name' => 'from-file'])->assertSuccessful();

    expect(lynx()->pages->findOne('template=form, name=from-file, include=all'))->not->toBeNull();
    File::deleteDirectory(dirname($path));
});

it('refuses a file it can\'t read as JSON on the command line', function (): void {
    $path = testingPath('form-transfer').'/broken.json';
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '{not json');

    $this->artisan('lynx:forms:import', ['path' => $path])->assertFailed();
    File::deleteDirectory(dirname($path));
});

it('downloads a form\'s structure from the forms list, for people who may build forms', function (): void {
    transferForm();
    $this->actingAs(new GenericUser(['id' => 1]));
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);

    Livewire::test(FormList::class)->assertSeeHtml('data-form-export="contact"');
    $response = $this->get(route('lynx.admin.forms.structure', 'contact'))->assertOk();

    expect(json_decode($response->streamedContent(), true))->toBe(exported())
        ->and($response->headers->get('Content-Disposition'))->toContain('contact.form.json');
    $this->get(route('lynx.admin.forms.structure', 'missing'))->assertNotFound();

    lynx()->run(SaveRole::class, ['name' => 'production', 'grants' => [['permission' => 'admin.access'], ['permission' => 'forms.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['production']]);
    $this->actingAs(new GenericUser(['id' => 2]));

    $this->get(route('lynx.admin.forms.structure', 'contact'))->assertForbidden();
    Livewire::test(FormList::class)->assertDontSeeHtml('data-form-export')->assertDontSeeHtml('data-form-import');
});

it('imports a form from the forms list and opens it in the editor', function (): void {
    transferForm();
    $this->actingAs(new GenericUser(['id' => 1]));
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    $file = UploadedFile::fake()->createWithContent('contact.form.json', json_encode(exported(), JSON_THROW_ON_ERROR));

    Livewire::test(FormList::class)->set('importFile', $file)->call('import')->assertHasErrors('importName');

    $page = fn () => lynx()->pages->findOne('template=form, name=contact-copy, include=all');
    Livewire::test(FormList::class)->set('importFile', $file)->set('importName', 'contact-copy')->call('import')
        ->assertHasNoErrors()->assertRedirect(route('lynx.admin.pages.edit', ['page' => $page()?->id]));

    expect(session('lynx-notify')['type'] ?? null)->toBe('success');
});

it('says a file is not a form export in the forms list', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);

    Livewire::test(FormList::class)->set('importFile', UploadedFile::fake()->createWithContent('notes.json', 'not json'))->call('import')
        ->assertHasErrors('importFile');
});
