<?php

declare(strict_types=1);

use LynxCms\Forms\Actions\InstallForms;
use LynxCms\Forms\FormSchema;

/*
 * The form builder's own fields are read only in the admin, so installing again brings their
 * label and help text up to date on sites installed by an earlier version.
 */

it('brings the label and help of its own fields up to date', function (): void {
    courseSite();
    lynx()->run(InstallForms::class);
    lynx()->fields->update(FormSchema::ONCE_PER_USER, ['label' => 'Older wording', 'settings' => ['description' => null]]);

    lynx()->run(InstallForms::class);

    [, , $label, $settings] = FormSchema::fields()[FormSchema::ONCE_PER_USER];
    $field = lynx()->fields->get(FormSchema::ONCE_PER_USER);

    expect($field->label)->toBe($label)
        ->and($settings['description'] ?? null)->not->toBeNull()
        ->and($field->config()['description'] ?? null)->toBe($settings['description']);
});

it('brings its block types up to date, so a field\'s condition shows as a group', function (): void {
    courseSite();
    lynx()->run(InstallForms::class);
    $types = FormSchema::blockTypes();
    $types[0]['fields'] = array_map(fn (array $field): array => array_diff_key($field, ['group' => true]), $types[0]['fields']);
    lynx()->fields->update(FormSchema::FIELDS, ['settings' => ['types' => $types]]);

    lynx()->run(InstallForms::class);

    expect(lynx()->fields->get(FormSchema::FIELDS)->config()['types'])->toBe(FormSchema::blockTypes());
});

it('explains a field\'s condition, with examples', function (): void {
    $condition = array_first(array_filter(FormSchema::blockTypes()[0]['fields'], fn (array $field): bool => isset($field['group'])));

    expect($condition['name'])->toBe(FormSchema::SHOW_IF)
        ->and($condition['group']['label'])->toBe('Condition')
        ->and($condition['group']['notes'])->toContain('>= 18')
        ->and(FormSchema::fields()[FormSchema::SHOW_IF][2])->toBe('Show only when the field')
        ->and(FormSchema::fields()[FormSchema::SHOW_IF_VALUES][2])->toBe('has the answer')
        ->and(FormSchema::fields()[FormSchema::SHOW_IF_VALUES][3]['description'])->toContain('comma');
});
