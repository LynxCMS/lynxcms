<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

/*
 * Superusers see, under a field's label, whether its text goes into the search index (ADR-0120):
 * the title always, other fields when the template marks them searchable and their type gives text.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->templates->update('course', ['add' => [['name' => 'summary', 'searchable' => true], ['name' => 'related', 'searchable' => true]]]);
    ['alpha' => $this->alpha] = createCourses('alpha');
    lynx()->run(SaveRole::class, ['name' => 'superuser', 'superuser' => true]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
});

it('tells superusers which fields are searchable', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(PageEditor::class, ['pageId' => $this->alpha->id])
        ->assertSeeHtml('data-searchable="title"')
        ->assertSeeHtml('data-searchable="summary"')
        ->assertDontSeeHtml('data-searchable="body"')
        ->assertDontSeeHtml('data-searchable="related"');
});

it('does not tell other editors', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view'], ['permission' => 'pages.edit']]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['writer']]);
    $this->actingAs(new GenericUser(['id' => 5]));

    Livewire::test(PageEditor::class, ['pageId' => $this->alpha->id])
        ->assertDontSeeHtml('data-searchable=');
});
