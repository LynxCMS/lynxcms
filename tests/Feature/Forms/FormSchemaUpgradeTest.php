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
