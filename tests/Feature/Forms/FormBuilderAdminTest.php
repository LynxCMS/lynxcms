<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Pages\Page;
use LynxCms\Forms\Actions\CreateForm;
use LynxCms\Forms\Admin\Livewire\FormList;
use LynxCms\Forms\Admin\Livewire\PreviewForm;
use LynxCms\Forms\FormSchema;
use LynxCms\Forms\Models\Submission;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;

beforeEach(function (): void {
    Storage::fake('local');
    courseSite();
    lynx()->forms()->register(QuoteForm::class);
    $this->form = lynx()->run(CreateForm::class, ['name' => 'contact', 'label' => ['default' => 'Contact us', 'pt' => 'Contacte-nos']])->valueAs(Page::class);
    lynx()->run(SavePage::class, ['id' => $this->form->id, 'values' => [FormSchema::FIELDS => [
        ['type' => 'text', 'values' => ['form_label' => ['default' => 'Your name'], 'form_required' => true]],
    ]]]);
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('lists builder and code forms on their own tab', function (): void {
    $this->get(route('lynx.admin.forms.builder'))->assertOk()->assertSee('Inbox')->assertSeeLivewire(FormList::class);

    Livewire::test(FormList::class)->assertSee('Contact us')->assertSee('unpublished')->assertSee('Quote request')->assertSee('code');
});

it('creates a form and opens it in the page editor', function (): void {
    $component = Livewire::test(FormList::class)->set('name', 'newsletter')->set('label', 'Newsletter')->call('create')->assertHasNoErrors();
    $page = lynx()->pages->findOne('template='.FormSchema::FORM_TEMPLATE.', name=newsletter, include=all');

    $component->assertRedirect(route('lynx.admin.pages.edit', $page?->id));
});

it('shows the action\'s errors when a name is taken', function (): void {
    Livewire::test(FormList::class)->set('name', 'quote')->set('label', 'Quote')->call('create')->assertHasErrors('name');
});

it('edits form pages in the page editor, with a preview link, and keeps the branch out of the tree', function (): void {
    $this->get(route('lynx.admin.pages.edit', $this->form->id))->assertOk()->assertSee('Preview form');
    $root = lynx()->pages->findOne('template='.FormSchema::ROOT_TEMPLATE.', include=all');
    $this->get(route('lynx.admin.pages.edit', $root?->id))->assertRedirect(route('lynx.admin.forms.builder'));

    Livewire::test(PageTree::class)->assertDontSee('Contact us');
});

it('previews a form, unpublished too, and sends nothing', function (): void {
    $this->get(route('lynx.admin.forms.preview', 'contact'))->assertOk()->assertSee('Your name');

    $preview = Livewire::test(PreviewForm::class, ['form' => 'contact'])->set('data.your_name', 'Ana');
    $this->travel(5)->seconds();
    $preview->call('submit')->assertSet('isSent', false)->assertSee('Preview: nothing was sent');

    expect(Submission::query()->count())->toBe(0);
});

it('keeps building forms to users who may manage them', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'production', 'grants' => [['permission' => 'admin.access'], ['permission' => 'forms.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['production']]);
    $this->actingAs(new GenericUser(['id' => 2]));

    Livewire::test(FormList::class)->assertSee('Contact us')->assertDontSee('New form')->call('create')->assertForbidden();
});
