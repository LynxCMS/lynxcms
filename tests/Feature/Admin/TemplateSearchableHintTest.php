<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\TemplateForm;

/*
 * The searchable checkbox says which searches find a page by the field's text. Pages without a URL
 * are left out of site search unless it names their template, so the label says so there.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('says admin and site search find pages of a template with a URL', function (): void {
    Livewire::test(TemplateForm::class, ['template' => 'course'])
        ->assertSee(__('lynx-admin::templates.row.searchable_check'))
        ->assertDontSee(__('lynx-admin::templates.row.searchable_without_url'));
});

it('says site search leaves out pages of a template without a URL', function (): void {
    lynx()->templates->update('course', ['has_url' => false]);

    Livewire::test(TemplateForm::class, ['template' => 'course'])
        ->assertSee(__('lynx-admin::templates.row.searchable_without_url'))
        ->assertDontSee(__('lynx-admin::templates.row.searchable_check'));
});
