<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use LynxCms\Media\Actions\StoreMedia;
use LynxCms\Media\Values\Pagefile;

/*
 * Temporary links to a page's files: signed and expiring, so a file can be handed to someone who
 * may not open its page (a gated download), and to nobody once the link runs out.
 */

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    courseSite();
    lynx()->fields->add('handouts', 'files', settings: ['private' => true]);
    lynx()->fields->add('brochure', 'files');
    lynx()->templates->update('course', ['add' => ['handouts', 'brochure']]);
    ['alpha' => $alpha, 'beta' => $beta] = createCourses('alpha', 'beta');
    $this->course = $alpha;
    $this->other = $beta;
});

/** Stores a file in a field of a course and saves it on the page. */
function signedFile(int $pageId, string $field, string $name, string $contents): void
{
    $entry = lynx()->run(StoreMedia::class, ['page' => $pageId, 'field' => $field, 'base64' => base64_encode($contents), 'filename' => $name])->value;
    $page = lynx()->pages->get($pageId);
    $page->{$field} = [['values' => $entry]];
    $page->save();
}

function signedHandout(): Pagefile
{
    return lynx()->pages->get(test()->course->id)->handouts->first();
}

it('streams a private file through a valid link, as a download', function (): void {
    signedFile($this->course->id, 'handouts', 'notes.pdf', '%PDF private');

    $response = $this->get(signedHandout()->temporaryUrl(now()->addHour()))->assertOk();

    expect($response->streamedContent())->toBe('%PDF private')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex');
});

it('serves the file even when visitors may not open the page', function (): void {
    signedFile($this->course->id, 'handouts', 'notes.pdf', '%PDF private');
    lynx()->pages->setStatus($this->course->id, add: ['unpublished']);

    $this->get(lynx()->pages->get($this->course->id)->handouts->first()->temporaryUrl(now()->addHour()))->assertOk();
});

it('refuses an expired link', function (): void {
    signedFile($this->course->id, 'handouts', 'notes.pdf', '%PDF private');
    $url = signedHandout()->temporaryUrl(now()->addMinute());

    $this->travel(2)->minutes();

    $this->get($url)->assertForbidden();
});

it('refuses a link that was changed', function (): void {
    signedFile($this->course->id, 'handouts', 'notes.pdf', '%PDF private');

    $this->get(signedHandout()->temporaryUrl(now()->addHour()).'x')->assertForbidden();
});

it('refuses a link pointed at another page', function (): void {
    signedFile($this->course->id, 'handouts', 'notes.pdf', '%PDF private');
    $url = str_replace('/'.$this->course->id.'/', '/'.$this->other->id.'/', signedHandout()->temporaryUrl(now()->addHour()));

    $this->get($url)->assertForbidden();
});

it('gives 404 for a signed link whose file is gone', function (): void {
    signedFile($this->course->id, 'handouts', 'notes.pdf', '%PDF private');
    $url = signedHandout()->temporaryUrl(now()->addHour());
    Storage::disk('local')->deleteDirectory('lynx/'.$this->course->id);

    $this->get($url)->assertNotFound();
});

it('makes temporary links to public files too', function (): void {
    signedFile($this->course->id, 'brochure', 'brochure.pdf', '%PDF public');

    $response = $this->get(lynx()->pages->get($this->course->id)->brochure->first()->temporaryUrl(now()->addHour()))->assertOk();

    expect($response->streamedContent())->toBe('%PDF public');
});
