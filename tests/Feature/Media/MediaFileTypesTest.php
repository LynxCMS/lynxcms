<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Pages\Page;
use LynxCms\Media\Actions\StoreMedia;
use LynxCms\Media\Fields\FilesType;

/*
 * Media files are served from the site's own address, so no field may allow files a web server
 * or a browser runs, and private files open in the browser only when they are plain media.
 */

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    courseSite();
    lynx()->fields->add('handouts', 'files', settings: ['private' => true, 'extensions' => 'pdf zip csv txt']);
    lynx()->templates->update('course', ['add' => ['handouts']]);
    ['alpha' => $alpha] = createCourses('alpha');
    $this->courseId = (int) $alpha->id;
});

/** Stores a private handout on the course and returns its address. */
function handoutUrl(string $name, string $contents): string
{
    $entry = lynx()->run(StoreMedia::class, ['page' => test()->courseId, 'field' => 'handouts', 'base64' => base64_encode($contents), 'filename' => $name])->value;
    $page = lynx()->pages->get(test()->courseId);
    assert($page instanceof Page);
    $page->handouts = [['values' => $entry]];
    $page->save();

    return (string) lynx()->pages->get(test()->courseId)?->handouts->first()?->url();
}

it('never lets a field allow files a server or a browser runs', function (string $extensions): void {
    expect(fn () => lynx()->fields->add('uploads', 'files', settings: ['extensions' => $extensions]))
        ->toThrow(ValidationException::class, 'can run code on your site');
})->with(['pdf php', 'phtml', 'html', 'svg', 'js', 'phar']);

it('refuses those files even where an older setting lists them', function (): void {
    expect(app(FilesType::class)->extensions(['extensions' => 'pdf php svg']))->toBe(['pdf']);
});

it('opens private PDFs in the browser, sandboxed and never sniffed', function (): void {
    $response = $this->get(handoutUrl('notes.pdf', '%PDF-1.4 private'))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Content-Security-Policy'))->toBe('sandbox');
});

it('downloads other private files instead of opening them', function (): void {
    $response = $this->get(handoutUrl('grades.csv', "<script>alert(1)</script>\n"))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});
