<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PagePicker;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;
use LynxCms\Forms\Actions\CreateForm;
use LynxCms\Forms\FormSchema;
use LynxCms\Forms\Livewire\FormComponent;
use LynxCms\Forms\Models\Submission;
use LynxCms\Mcp\LynxServer;
use LynxCms\Mcp\Tools\ActionTool;

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
});

/** A published builder form: contact details, then a step with a group of people and a file. */
function flowForm(): Page
{
    $page = lynx()->run(CreateForm::class, ['name' => 'casting', 'label' => ['default' => 'Casting', 'pt' => 'Casting']])->valueAs(Page::class);
    lynx()->run(SavePage::class, ['id' => $page->id, 'status' => [], 'values' => [FormSchema::FIELDS => [
        ['type' => 'text', 'values' => ['form_label' => ['default' => 'Name', 'pt' => 'Nome'], 'form_required' => true]],
        ['type' => 'email', 'values' => ['form_label' => ['default' => 'Email'], 'form_required' => true]],
        ['type' => 'step', 'values' => ['form_label' => ['default' => 'Your team', 'pt' => 'A sua equipa']]],
        ['type' => 'group', 'values' => ['form_label' => ['default' => 'People', 'pt' => 'Pessoas'], 'form_name' => 'people']],
        ['type' => 'text', 'depth' => 1, 'values' => ['form_label' => ['default' => 'Role', 'pt' => 'Função'], 'form_name' => 'role', 'form_required' => true]],
        ['type' => 'file', 'values' => ['form_label' => ['default' => 'Showreel'], 'form_name' => 'reel', 'form_extensions' => 'pdf']],
    ]]]);

    return $page;
}

it('sends a builder form like a code form, in the visitor\'s language', function (): void {
    flowForm();
    $component = lynx()->language('pt', fn () => Livewire::test(FormComponent::class, ['form' => 'casting']))
        ->assertSee('Nome')->assertSee('Passo 1 de 2')
        ->set('data.name', 'Rita')->set('data.email', 'rita@example.com')->call('next')
        ->assertSee('Pessoas')->call('addRow', 'people')->set('data.people.0.role', 'Câmara')
        ->set('files.reel', [UploadedFile::fake()->create('reel.pdf', 20, 'application/pdf')]);
    $this->travel(5)->seconds();

    $component->call('submit')->assertHasNoErrors()->assertSet('isSent', true);

    $submission = Submission::query()->sole();
    expect($submission->form)->toBe('casting')
        ->and($submission->data)->toMatchArray(['name' => 'Rita', 'people' => [['role' => 'Câmara']]])
        ->and($submission->files)->toHaveCount(1);
});

it('lets editors pick hidden forms with the form field', function (): void {
    flowForm();
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(PagePicker::class, ['multiple' => false, 'templates' => [FormSchema::FORM_TEMPLATE]])
        ->set('search', 'Cast')->assertSee('Casting');
});

it('builds a form for an agent through MCP', function (): void {
    LynxServer::tool(new ActionTool(CreateForm::class), ['name' => 'newsletter', 'label' => ['default' => 'Newsletter']])->assertOk();
    $id = lynx()->pages->findOne('template='.FormSchema::FORM_TEMPLATE.', name=newsletter, include=all')?->id;

    LynxServer::tool(new ActionTool(SavePage::class), ['id' => $id, 'status' => [], 'values' => [FormSchema::FIELDS => [
        ['type' => 'email', 'values' => ['form_label' => ['default' => 'Email'], 'form_required' => true]],
    ]]])->assertOk();

    expect(lynx()->forms()->get('newsletter')?->fields()[0]->name)->toBe('email');
});
