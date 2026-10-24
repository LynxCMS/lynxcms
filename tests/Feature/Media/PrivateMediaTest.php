<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\FieldForm;
use LynxCms\Core\Actions\Content\ImportContent;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Content\ContentExporter;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGates;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\Core\Pages\Visibility\VisibilityRule;
use LynxCms\Media\Actions\PruneMedia;
use LynxCms\Media\Actions\StoreMedia;

/*
 * Private media fields (ADR-0077): their files live on a private disk and are served through a
 * Lynx route that asks the questions the page's own URL asks.
 */

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    courseSite();
    lynx()->fields->add('handouts', 'files', settings: ['private' => true]);
    lynx()->fields->add('slides', 'images', settings: ['private' => true]);
    lynx()->fields->add('brochure', 'files');
    lynx()->templates->update('course', ['add' => ['handouts', 'slides', 'brochure']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $this->course = $alpha;
});

/** Stores a file in a field of the course and saves it on the page; returns the stored entry. */
function privateFile(string $field, string $name, string $contents): array
{
    $entry = lynx()->run(StoreMedia::class, ['page' => test()->course->id, 'field' => $field, 'base64' => base64_encode($contents), 'filename' => $name])->value;
    $page = lynx()->pages->get(test()->course->id);
    $page->{$field} = [['values' => $entry]];
    $page->save();

    return $entry;
}

function coursePage(): Page
{
    return lynx()->pages->get(test()->course->id);
}

it('keeps private files on the private disk, and public ones where they were', function (): void {
    $handout = privateFile('handouts', 'notes.pdf', '%PDF private');
    $brochure = privateFile('brochure', 'brochure.pdf', '%PDF public');

    Storage::disk('local')->assertExists('lynx/'.$handout['file']);
    Storage::disk('public')->assertMissing('lynx/'.$handout['file']);
    Storage::disk('public')->assertExists('lynx/'.$brochure['file']);
});

it('points private files at the Lynx route, and public ones at the disk', function (): void {
    $handout = privateFile('handouts', 'notes.pdf', '%PDF private');
    privateFile('brochure', 'brochure.pdf', '%PDF public');

    expect(coursePage()->handouts->first()->url())->toBe(route('lynx.media', ['page' => test()->course->id, 'file' => basename($handout['file'])]))
        ->and(coursePage()->brochure->first()->url())->toContain('/storage/');
});

it('serves a private file to visitors who may open its page', function (): void {
    privateFile('handouts', 'notes.pdf', '%PDF private');

    $response = $this->get(coursePage()->handouts->first()->url())->assertOk();

    expect($response->streamedContent())->toBe('%PDF private')
        ->and($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex');
});

it('answers a private file as its page\'s gate answers', function (): void {
    privateFile('handouts', 'notes.pdf', '%PDF private');
    app(PageGates::class)->add(fn (Page $page, Request $request): Redirector|RedirectResponse|null => $page->id === test()->course->id && auth()->guest() ? redirect('/members-login') : null);

    $this->get(coursePage()->handouts->first()->url())->assertRedirect('/members-login');
    $this->actingAs(new GenericUser(['id' => 7]))->get(coursePage()->handouts->first()->url())->assertOk();
});

it('hides the files of an unpublished page from visitors, but not from staff who may view it', function (): void {
    privateFile('handouts', 'notes.pdf', '%PDF private');
    $url = coursePage()->handouts->first()->url();
    $page = coursePage()->setStatus(PageStatus::UNPUBLISHED);
    $page->save();

    $this->get($url)->assertNotFound();

    lynx()->run(SaveRole::class, ['name' => 'viewer', 'grants' => [['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '3', 'roles' => ['viewer']]);
    $this->actingAs(new GenericUser(['id' => 3]))->get($url)->assertOk();
});

it('refuses names that are not a file of that page', function (): void {
    privateFile('handouts', 'notes.pdf', '%PDF private');

    $this->get(route('lynx.media', ['page' => test()->course->id, 'file' => 'missing.pdf']))->assertNotFound();
    $this->get('/lynx-media/'.test()->course->id.'/..%2F..%2F.env')->assertNotFound();
    $this->get(route('lynx.media', ['page' => 999999, 'file' => 'notes.pdf']))->assertNotFound();
});

it('makes and serves image sizes of private images on the private disk', function (): void {
    privateFile('slides', 'slide.jpg', jpeg(800, 600));

    $thumb = coursePage()->slides->first()->size(200, 200);
    $url = $thumb->url();

    expect($url)->toContain('/lynx-media/');
    $this->get($url)->assertOk();
    Storage::disk('local')->assertExists('lynx/'.$thumb->file());
    Storage::disk('public')->assertMissing('lynx/'.$thumb->file());
});

it('moves a field\'s files when it turns private, and back', function (): void {
    $brochure = privateFile('brochure', 'brochure.pdf', '%PDF moving');

    lynx()->fields->update('brochure', ['settings' => ['private' => true]]);

    Storage::disk('local')->assertExists('lynx/'.$brochure['file']);
    Storage::disk('public')->assertMissing('lynx/'.$brochure['file']);
    expect(coursePage()->brochure->first()->url())->toContain('/lynx-media/');

    lynx()->fields->update('brochure', ['settings' => ['private' => false]]);

    Storage::disk('public')->assertExists('lynx/'.$brochure['file']);
    Storage::disk('local')->assertMissing('lynx/'.$brochure['file']);
});

it('moves nothing on a dry run', function (): void {
    $brochure = privateFile('brochure', 'brochure.pdf', '%PDF staying');

    lynx()->fields->update('brochure', ['settings' => ['private' => true]], dryRun: true);

    Storage::disk('public')->assertExists('lynx/'.$brochure['file']);
    Storage::disk('local')->assertMissing('lynx/'.$brochure['file']);
});

it('prunes unused files on both disks', function (): void {
    $kept = privateFile('handouts', 'kept.pdf', '%PDF kept');
    Storage::disk('local')->put('lynx/'.test()->course->id.'/orphan.pdf', 'orphan');

    lynx()->run(PruneMedia::class, ['older_than_hours' => 0]);

    Storage::disk('local')->assertExists('lynx/'.$kept['file']);
    Storage::disk('local')->assertMissing('lynx/'.test()->course->id.'/orphan.pdf');
});

it('deletes a page\'s private files with the page', function (): void {
    $handout = privateFile('handouts', 'notes.pdf', '%PDF private');

    lynx()->pages->trash(coursePage());
    lynx()->pages->delete(test()->course->id);

    Storage::disk('local')->assertMissing('lynx/'.$handout['file']);
});

it('hides a private file from visitors a visibility rule keeps from its page', function (): void {
    privateFile('handouts', 'notes.pdf', '%PDF private');
    app(PageVisibility::class)->add(new class implements VisibilityRule
    {
        public function apply(Builder $query): void {}

        public function allows(Page $page): bool
        {
            return auth()->check();
        }
    });

    $this->get(coursePage()->handouts->first()->url())->assertNotFound();
});

it('deletes the old disk\'s image sizes when a field moves', function (): void {
    lynx()->fields->add('photos', 'images');
    lynx()->templates->update('course', ['add' => ['photos']]);
    $photo = privateFile('photos', 'photo.jpg', jpeg(800, 600));
    $size = coursePage()->photos->first()->size(100, 100)->file(); // made now, on the public disk
    Storage::disk('public')->assertExists('lynx/'.$size);

    lynx()->fields->update('photos', ['settings' => ['private' => true]]);

    Storage::disk('public')->assertMissing('lynx/'.$size);
    Storage::disk('public')->assertMissing('lynx/'.$photo['file']);
    Storage::disk('local')->assertExists('lynx/'.$photo['file']);
});

it('keeps a private field private inside repeater items, and moves those files too', function (): void {
    lynx()->fields->add('sheets', 'files');
    lynx()->fields->add('modules', 'repeater', settings: ['fields' => [['name' => 'title'], ['name' => 'sheets']]]);
    lynx()->templates->update('course', ['add' => ['modules']]);
    $entry = lynx()->run(StoreMedia::class, ['page' => test()->course->id, 'field' => 'sheets', 'base64' => base64_encode('%PDF sheet'), 'filename' => 'sheet.pdf'])->value;
    $page = coursePage();
    $page->modules = [['values' => ['title' => 'One', 'sheets' => [['values' => $entry]]]]];
    $page->save();

    lynx()->fields->update('sheets', ['settings' => ['private' => true]]);

    Storage::disk('local')->assertExists('lynx/'.$entry['file']);
    expect(coursePage()->modules->first()->sheets->first()->url())->toContain('/lynx-media/');
});

it('carries private files in content packages and keeps them private on import', function (): void {
    $handout = privateFile('handouts', 'notes.pdf', '%PDF packaged');
    $package = testingPath('private-media-package');
    File::deleteDirectory($package);

    app(ContentExporter::class)->export($package);
    expect(File::get($package.'/files/'.$handout['file']))->toBe('%PDF packaged');

    lynx()->pages->delete('/courses/alpha/');
    lynx()->run(ImportContent::class, ['path' => $package]);

    $imported = lynx()->pages->get('/courses/alpha/')->handouts->first();
    Storage::disk('local')->assertExists('lynx/'.$imported->file());
    Storage::disk('public')->assertMissing('lynx/'.$imported->file());
    File::deleteDirectory($package);
});

it('offers the Private setting in the admin\'s field form, and moves the files when it is saved', function (): void {
    $brochure = privateFile('brochure', 'brochure.pdf', '%PDF via admin');
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(FieldForm::class, ['field' => 'brochure'])
        ->assertSee('Keep the files private')
        ->set('settings.private', true)
        ->call('save')
        ->assertHasNoErrors();

    Storage::disk('local')->assertExists('lynx/'.$brochure['file']);
});
