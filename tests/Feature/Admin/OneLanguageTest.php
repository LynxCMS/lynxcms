<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\FieldForm;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Pages\Page;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Admin\Livewire\SubmissionView;
use LynxCms\Forms\Models\Submission;
use LynxCms\Menus\Admin\MenuEditor;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;

/*
 * A site with one language never mentions languages: translation, fallbacks and "default" mean
 * nothing until a second language is added, so the admin leaves them out.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->languages->delete('pt');
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    $this->actingAs(new GenericUser(['id' => 1]));
});

function oneLanguageCourse(): Page
{
    return createCourses('alpha')['alpha'];
}

it('reports one language', function (): void {
    expect(lynx()->languages->isMultilingual())->toBeFalse();

    lynx()->languages->add('pt', 'Português', fallback: 'default');

    expect(lynx()->languages->isMultilingual())->toBeTrue();
});

it('leaves the languages choice out of a new field', function (): void {
    Livewire::test(FieldForm::class)
        ->assertSeeHtml('data-kind="text"')
        ->assertDontSeeHtml('name="field-translatable"');
});

it('leaves translatable out of field types in lists and the editor', function (): void {
    $course = oneLanguageCourse();

    $this->get('/lynx/fields')->assertOk()->assertDontSee(', translatable');
    $this->get(route('lynx.admin.pages.edit', $course->id))->assertOk()->assertDontSee(', translatable');
});

it('names a page without languages in its settings', function (): void {
    $course = oneLanguageCourse();

    $this->get(route('lynx.admin.pages.edit', ['page' => $course->id, 'tab' => 'settings']))->assertOk()
        ->assertDontSee('Names and languages')
        ->assertDontSee('default language only')
        ->assertDontSeeText('Default');
    $this->get(route('lynx.admin.pages.edit', ['page' => lynx()->pages->get('/')?->id, 'tab' => 'settings']))
        ->assertDontSee('language prefix');
});

it('records changes without language codes', function (): void {
    $course = oneLanguageCourse();
    $course->title = 'Alpha, again';

    expect(lynx()->pages->save($course, dryRun: true)->changes->summary()[0])->toContain('title')->not->toContain('[default]');
});

it('leaves the default language out of template field settings', function (): void {
    $this->get(route('lynx.admin.templates.edit', 'course'))->assertOk()
        ->assertSee('Required')
        ->assertDontSee('in the default language');
});

it('describes module screens without languages', function (string $route, string $phrase): void {
    $this->get(route($route))->assertOk()->assertDontSee($phrase);
})->with([
    'menus' => ['lynx.admin.menus', 'in every language'],
    'sitemap' => ['lynx.admin.sitemap', 'in every language'],
    'static export' => ['lynx.admin.static', 'in every language'],
    'SEO audit' => ['lynx.admin.seo.audit', 'in this language'],
]);

it('leaves languages out of the menu item form', function (): void {
    lynx()->menus()->create('top', 'Top');
    $top = (int) lynx()->menus()->page('top')?->id;

    Livewire::test(MenuEditor::class, ['menuId' => $top])
        ->dispatch('menu-item-new', parent: $top)
        ->set('item.kind', 'page')
        ->assertSee('Leave empty to use the page title.')
        ->assertDontSee('in each language');
});

it('leaves the language out of a form submission', function (): void {
    Storage::fake('local');
    lynx()->forms()->register(QuoteForm::class);
    lynx()->run(SubmitForm::class, ['form' => 'quote', 'data' => [
        'type' => 'film', 'name' => 'Ana Lima', 'email' => 'ana@example.com', 'consent' => true,
        'brief' => [UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')],
    ]]);

    Livewire::test(SubmissionView::class, ['uuid' => Submission::query()->sole()->uuid])
        ->assertSee('Ana Lima')
        ->assertDontSee('in Default');
});

it('keeps the language wording with several languages', function (): void {
    lynx()->languages->add('pt', 'Português', fallback: 'default');
    $course = oneLanguageCourse();

    Livewire::test(FieldForm::class)->assertSeeHtml('name="field-translatable"');
    $this->get(route('lynx.admin.pages.edit', ['page' => $course->id, 'tab' => 'settings']))->assertSee('Names and languages');
    $this->get(route('lynx.admin.templates.edit', 'course'))->assertSee('in the default language');
    $this->get(route('lynx.admin.menus'))->assertSee('in every language');
});
