<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\BrandingForm;
use LynxCms\Admin\Livewire\LanguageForm;

/*
 * Help text under a field is a step smaller than the field's own text, the same everywhere in the
 * admin: the lynx-field-help class, defined once in the admin's stylesheet.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('defines the help text style once, smaller than the fields', function (): void {
    $css = File::get(dirname(__DIR__, 3).'/packages/admin/resources/css/admin.css');

    expect($css)->toContain('.lynx-field-help')
        ->toContain('text-[0.8125rem]');
});

it('gives the help under settings fields the help style', function (string $component): void {
    Livewire::test($component)->assertSeeHtml('class="lynx-field-help"');
})->with([
    'branding' => [BrandingForm::class],
    'a language' => [LanguageForm::class],
]);

it('gives a field description in the page editor the help style', function (): void {
    lynx()->fields->update('summary', ['settings' => ['description' => 'A sentence or two for lists.']]);
    $course = createCourses('alpha')['alpha'];

    $this->get(route('lynx.admin.pages.edit', $course->id))->assertOk()
        ->assertSee('<p class="lynx-field-help mt-1">A sentence or two for lists.</p>', false);
});

it('gives the field description component the help style', function (): void {
    expect(Blade::render('<x-lynx-admin::ui.field-description>Help.</x-lynx-admin::ui.field-description>'))
        ->toContain('lynx-field-help');
});
