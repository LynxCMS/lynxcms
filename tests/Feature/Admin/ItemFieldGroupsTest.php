<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Fields\Items\Subfield;
use LynxCms\Core\Fields\Items\SubfieldGroups;

/*
 * A group of an item's fields: a subfield entry with "group" starts a box with a heading, help
 * above and notes below, holding it and the fields after it until the next group.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('role', 'text', label: 'Role');
    lynx()->fields->add('when_field', 'text', label: 'When field');
    lynx()->fields->add('when_value', 'text', label: 'When value');
    lynx()->fields->add('blocks', 'matrix', settings: ['types' => [
        ['slug' => 'person', 'label' => 'Person', 'fields' => [
            ['name' => 'role'],
            ['name' => 'when_field', 'width' => 50, 'group' => [
                'label' => ['default' => 'Condition', 'pt' => 'Condição'],
                'description' => 'Leave both empty to always show it.',
                'notes' => 'Example: job_title and other.',
            ]],
            ['name' => 'when_value', 'width' => 50],
        ]],
    ]]);
    lynx()->templates->update('course', ['add' => ['blocks']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $alpha->blocks = [['type' => 'person', 'values' => ['role' => 'Host']]];
    $alpha->save();
    $this->alphaId = (int) $alpha->id;
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('splits subfields into groups, each running to the next', function (): void {
    $definition = lynx()->fields->get('blocks');
    $groups = SubfieldGroups::of($definition->fieldType()->subfields($definition->config(), 'person'));

    expect($groups)->toHaveCount(2)
        ->and($groups[0]->label)->toBe('')
        ->and(array_map(fn (Subfield $s): string => $s->name(), $groups[0]->subfields))->toBe(['role'])
        ->and($groups[1]->label)->toBe('Condition')
        ->and($groups[1]->description)->toBe('Leave both empty to always show it.')
        ->and($groups[1]->notes)->toBe('Example: job_title and other.')
        ->and(array_map(fn (Subfield $s): string => $s->name(), $groups[1]->subfields))->toBe(['when_field', 'when_value']);
});

it('shows a group as a box with its heading, help and notes', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => $this->alphaId])
        ->assertSeeHtml('data-item-group')->assertSeeInOrder(['Role', 'Condition', 'Leave both empty to always show it.', 'When field', 'When value', 'Example: job_title and other.']);
});

it('refuses a group without a heading', function (): void {
    lynx()->fields->update('blocks', ['settings' => ['types' => [
        ['slug' => 'person', 'label' => 'Person', 'fields' => [['name' => 'role', 'group' => ['description' => 'No heading']]]],
    ]]]);
})->throws(ValidationException::class);
