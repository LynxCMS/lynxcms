<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use LynxCms\Admin\Editor\LinkFiles;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Media\Actions\StoreMedia;

beforeEach(function (): void {
    Storage::fake('public');
    courseSite();
    lynx()->fields->add('poster', 'images', settings: ['max_files' => 1]);
    lynx()->fields->add('downloads', 'files');
    lynx()->templates->update('course', ['add' => ['poster', 'downloads']]);
    $this->alphaId = (int) createCourses('alpha')['alpha']->id;
});

function storedFile(string $field, string $name, string $contents): array
{
    $entry = lynx()->run(StoreMedia::class, ['page' => test()->alphaId, 'field' => $field, 'base64' => base64_encode($contents), 'filename' => $name])->value;

    return ['values' => $entry];
}

it('offers the page\'s files and images to the rich text link dialog', function (): void {
    lynx()->run(SavePage::class, ['id' => test()->alphaId, 'values' => [
        'downloads' => [storedFile('downloads', 'Brochure.pdf', '%PDF-1.4 test')],
        'poster' => [storedFile('poster', 'Poster.jpg', jpeg(200, 100))],
    ]]);

    $files = app(LinkFiles::class)->for(lynx()->pages->get(test()->alphaId));

    expect($files)->toHaveCount(2)
        ->and(implode(' ', array_column($files, 'name')))->toMatch('/^poster-\w+\.jpg brochure-\w+\.pdf$/')
        ->and($files[0]['url'])->toStartWith('/')->toContain('/'.test()->alphaId.'/poster-');
});

it('offers files on this site by path, so links survive a change of domain', function (): void {
    Storage::fake('public', ['url' => url('/storage')]);
    lynx()->run(SavePage::class, ['id' => test()->alphaId, 'values' => ['downloads' => [storedFile('downloads', 'Brochure.pdf', '%PDF-1.4 test')]]]);

    expect(app(LinkFiles::class)->for(lynx()->pages->get(test()->alphaId))[0]['url'])->toStartWith('/storage/');
});

it('offers nothing for a page without files', function (): void {
    expect(app(LinkFiles::class)->for(lynx()->pages->get(test()->alphaId)))->toBe([]);
});
