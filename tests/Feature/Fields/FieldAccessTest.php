<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\FieldForm;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Admin\Livewire\TemplateForm;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Fields\UpdateField;
use LynxCms\Core\Actions\Pages\PublishDraft;
use LynxCms\Core\Actions\Pages\RestoreRevision;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Actions\Templates\UpdateTemplate;
use LynxCms\Core\Agents\AgentToolkit;
use LynxCms\Core\Fields\FieldAccess;
use LynxCms\Core\Pages\Page;

/*
 * Field access per role (ADR-0151): a field lists the roles that may see it and the roles that may
 * edit it. Others don't see it in the admin or through agents, or see it read only; their saves
 * are refused. Superusers and the site's code always may. Visitors and templates are not affected.
 */

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    $editing = [['permission' => 'admin.access'], ['permission' => 'pages.view'], ['permission' => 'pages.edit']];
    lynx()->run(SaveRole::class, ['name' => 'editor-pricing', 'grants' => $editing]);
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => $editing]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['editor-pricing']]);
    lynx()->run(AssignRoles::class, ['user' => '3', 'roles' => ['writer']]);
    lynx()->fields->update('price', ['settings' => ['access' => ['view' => ['writer'], 'edit' => ['editor-pricing']]]]);
    lynx()->fields->update('summary', ['settings' => ['access' => ['view' => ['editor-pricing']]]]);
});

function accessAlpha(): Page
{
    return lynx()->pages->get('/courses/alpha/') ?? throw new RuntimeException('No alpha.');
}

/** @param  array<string, mixed>  $values */
function saveAsUser(int $user, array $values): mixed
{
    return app(ActionRunner::class)->actingAs(Actor::user($user), fn (): ActionResult => lynx()->run(SavePage::class, ['id' => accessAlpha()->id, 'values' => $values]));
}

it('lets the roles a field names edit it, and refuses the others', function (): void {
    saveAsUser(2, ['price' => 120]);

    expect(accessAlpha()->get('price'))->toBe(120)
        ->and(fn (): mixed => saveAsUser(3, ['price' => 99]))->toThrow(ValidationException::class);

    saveAsUser(3, ['title' => ['default' => 'Alpha, renamed']]);

    expect((string) accessAlpha()->get('title'))->toBe('Alpha, renamed')->and(accessAlpha()->get('price'))->toBe(120);
});

it('lets superusers and the site\'s code edit any field', function (): void {
    saveAsUser(1, ['price' => 10]);
    lynx()->run(SavePage::class, ['id' => accessAlpha()->id, 'values' => ['price' => 20]]);

    expect(accessAlpha()->get('price'))->toBe(20);
});

it('answers who may see and edit a field', function (): void {
    $access = app(FieldAccess::class);
    $price = lynx()->fields->get('price');
    $summary = lynx()->fields->get('summary');

    expect($access->canEdit($price, Actor::user(2)))->toBeTrue()
        ->and($access->canEdit($price, Actor::user(3)))->toBeFalse()
        ->and($access->canView($price, Actor::user(3)))->toBeTrue()
        ->and($access->canView($summary, Actor::user(3)))->toBeFalse()
        ->and($access->canEdit($summary, Actor::user(3)))->toBeFalse()
        ->and($access->canView($summary, Actor::user(2)))->toBeTrue()
        ->and($access->canView($summary, Actor::user(1)))->toBeTrue()
        ->and($access->canEdit($summary, Actor::cli()))->toBeTrue();
});

it('hides a field from roles that may not see it in the page editor, and shows it read only to roles that may not edit it', function (): void {
    test()->actingAs(new GenericUser(['id' => 3]));

    Livewire::test(PageEditor::class, ['pageId' => accessAlpha()->id])
        ->assertDontSeeHtml('data-field="summary"')
        ->assertSeeHtml('data-field="price"')
        ->assertSeeHtml('data-restricted="true"')
        ->assertDontSeeHtml('id="input-price-default"');

    test()->actingAs(new GenericUser(['id' => 2]));

    Livewire::test(PageEditor::class, ['pageId' => accessAlpha()->id])
        ->assertSeeHtml('data-field="summary"')
        ->assertSeeHtml('id="input-price-default"');
});

it('leaves out of an agent\'s page the fields its role may not see', function (): void {
    lynx()->run(SavePage::class, ['id' => accessAlpha()->id, 'values' => ['summary' => ['default' => 'Secret summary']]]);
    $page = app(ActionRunner::class)->actingAs(Actor::mcp(3), fn () => app(AgentToolkit::class)->getPage(accessAlpha()->id));

    expect($page['fields'] ?? [])->not->toHaveKey('summary')->toHaveKey('price')
        ->and(json_encode($page))->not->toContain('Secret summary');

    $seen = app(ActionRunner::class)->actingAs(Actor::mcp(2), fn () => app(AgentToolkit::class)->getPage(accessAlpha()->id));

    expect($seen['fields'] ?? [])->toHaveKey('summary');
});

it('tells an agent which fields it may not edit or see', function (): void {
    $schema = app(ActionRunner::class)->actingAs(Actor::mcp(3), fn () => app(AgentToolkit::class)->describeSchema());
    $fields = collect(collect($schema['templates'])->firstWhere('name', 'course')['fields']);

    expect($fields->firstWhere('name', 'price')['editable'] ?? null)->toBeFalse()
        ->and($fields->firstWhere('name', 'summary'))->toBeNull();
});

it('refuses roles the site lacks', function (): void {
    lynx()->fields->update('price', ['settings' => ['access' => ['edit' => ['nobody']]]]);
})->throws(ValidationException::class);

it('manages access on the field form, a role per row, Edit off for roles that can\'t edit pages', function (): void {
    test()->actingAs(new GenericUser(['id' => 1]));
    lynx()->run(SaveRole::class, ['name' => 'reader', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]);

    Livewire::test(FieldForm::class, ['field' => 'body'])
        ->assertSet('manageAccess', false)
        ->assertDontSeeHtml('data-access-table')
        ->set('manageAccess', true)
        ->assertSeeHtml('data-access-edit="writer"')
        ->assertSeeHtml('data-access-none')
        ->assertDontSeeHtml('data-access-edit="superuser"')
        ->assertSeeHtml('data-access-edit="reader" disabled')
        ->set('editRoles', ['writer'])
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->fields->get('body')->config()['access'] ?? null)->toBe(['view' => [], 'edit' => ['writer']]);
});

it('lets only superusers see a managed field with no role ticked', function (): void {
    lynx()->fields->update('body', ['settings' => ['access' => ['view' => [], 'edit' => []]]]);
    $access = app(FieldAccess::class);
    $body = lynx()->fields->get('body');

    expect($access->canView($body, Actor::user(2)))->toBeFalse()
        ->and($access->canView($body, Actor::user(1)))->toBeTrue()
        ->and($access->isManaged($body))->toBeTrue()
        ->and($access->isManaged(lynx()->fields->get('title')))->toBeFalse();
});

it('stops managing a field\'s access on the field form', function (): void {
    test()->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(FieldForm::class, ['field' => 'price'])
        ->assertSet('manageAccess', true)
        ->set('manageAccess', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->fields->get('price')->config())->not->toHaveKey('access');
});

it('lets a role publish a draft and restore a revision of a page with a field it may not edit', function (): void {
    saveAsUser(2, ['price' => 120]);
    app(ActionRunner::class)->actingAs(Actor::user(3), fn () => lynx()->pages->saveDraft(accessAlpha(), ['title' => ['default' => 'Alpha draft']]));

    app(ActionRunner::class)->actingAs(Actor::user(3), fn (): ActionResult => lynx()->run(PublishDraft::class, ['page' => accessAlpha()->id]));

    expect((string) accessAlpha()->get('title'))->toBe('Alpha draft')->and(accessAlpha()->get('price'))->toBe(120);

    $older = lynx()->pages->revisions(accessAlpha())[1]['id'];
    app(ActionRunner::class)->actingAs(Actor::user(3), fn (): ActionResult => lynx()->run(RestoreRevision::class, ['page' => accessAlpha()->id, 'revision' => $older]));

    expect(accessAlpha()->get('price'))->toBe(120);
});

it('lets only superusers change a field\'s access or lock', function (array $settings): void {
    lynx()->run(SaveRole::class, ['name' => 'schema-editor', 'grants' => [['permission' => 'admin.access'], ['permission' => 'schema.manage']]]);
    lynx()->run(AssignRoles::class, ['user' => '4', 'roles' => ['schema-editor']]);

    app(ActionRunner::class)->actingAs(Actor::user(4), fn (): ActionResult => lynx()->run(UpdateField::class, ['field' => 'price', 'settings' => $settings]));
})->with([
    'access removed' => [['access' => null]],
    'access widened' => [['access' => ['view' => ['writer'], 'edit' => ['writer']]]],
    'lock changed' => [['locked' => true]],
])->throws(AuthorizationException::class);

it('lets other roles with schema.manage save a field without touching its access', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'schema-editor', 'grants' => [['permission' => 'admin.access'], ['permission' => 'schema.manage']]]);
    lynx()->run(AssignRoles::class, ['user' => '4', 'roles' => ['schema-editor']]);
    $access = lynx()->fields->get('price')->config()['access'];

    app(ActionRunner::class)->actingAs(Actor::user(4), fn (): ActionResult => lynx()->run(UpdateField::class, ['field' => 'price', 'label' => 'Cost', 'settings' => ['access' => $access, 'locked' => null]]));

    expect(lynx()->fields->get('price')->config()['access'])->toBe($access);
});

it('leaves the Access tab and the lock out of the field form for roles that are not superusers', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'schema-editor', 'grants' => [['permission' => 'admin.access'], ['permission' => 'schema.manage']]]);
    lynx()->run(AssignRoles::class, ['user' => '4', 'roles' => ['schema-editor']]);
    test()->actingAs(new GenericUser(['id' => 4]));
    $access = lynx()->fields->get('price')->config()['access'];

    Livewire::test(FieldForm::class, ['field' => 'price'])
        ->assertDontSeeHtml('data-field-locked')
        ->assertDontSeeHtml('data-field-tab="access"')
        ->set('label.default', 'Cost')
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->fields->get('price')->config()['access'])->toBe($access);
});

function schemaEditor(): Actor
{
    lynx()->run(SaveRole::class, ['name' => 'schema-editor', 'grants' => [['permission' => 'admin.access'], ['permission' => 'schema.manage']]]);
    lynx()->run(AssignRoles::class, ['user' => '4', 'roles' => ['schema-editor']]);

    return Actor::user(4);
}

it('lets only superusers lock or unlock a field on one template', function (array $change): void {
    lynx()->templates->update('course', ['add' => [['name' => 'summary', 'locked' => true]]]);

    app(ActionRunner::class)->actingAs(schemaEditor(), fn (): ActionResult => lynx()->run(UpdateTemplate::class, ['template' => 'course', ...$change]));
})->with([
    'unlocked' => [['add' => [['name' => 'summary', 'locked' => false]]]],
    'dropped from the full field list' => [['fields' => [['name' => 'title', 'required' => true], 'summary', 'body', 'price', 'related']]],
    'another field locked' => [['add' => [['name' => 'body', 'locked' => true]]]],
])->throws(AuthorizationException::class);

it('lets other roles with schema.manage change a template without touching its locks', function (): void {
    lynx()->templates->update('course', ['add' => [['name' => 'summary', 'locked' => true]]]);

    app(ActionRunner::class)->actingAs(schemaEditor(), fn (): ActionResult => lynx()->run(UpdateTemplate::class, ['template' => 'course', 'add' => [['name' => 'summary', 'locked' => true, 'label' => 'Short text'], ['name' => 'body', 'searchable' => true]]]));

    expect(lynx()->templates->fields('course')['summary']->isLocked())->toBeTrue();
});

it('shows the Locked box of a template\'s field row read only to roles that are not superusers', function (): void {
    schemaEditor();
    test()->actingAs(new GenericUser(['id' => 4]));

    Livewire::test(TemplateForm::class, ['template' => 'course'])
        ->assertSeeHtml('id="override-summary-locked" type="checkbox" wire:model="rows.summary.locked"  disabled');

    test()->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(TemplateForm::class, ['template' => 'course'])
        ->assertDontSeeHtml('wire:model="rows.summary.locked"  disabled');
});
