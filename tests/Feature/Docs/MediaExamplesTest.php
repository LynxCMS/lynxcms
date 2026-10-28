<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use LynxCms\Media\Downloads\HostResolver;

/*
 * The examples on Images and Files are the files in docs/snippets/media, shown with VitePress
 * includes. They run here on a site with the page and field they name.
 */

const MEDIA_SNIPPETS = __DIR__.'/../../../docs/snippets/media';

beforeEach(function (): void {
    Storage::fake('public');
    installLynx();
    lynx()->languages->update('default', ['code' => 'en', 'name' => 'English']);
    lynx()->fields->add('gallery', 'images');
    lynx()->templates->add('project', fields: ['title', 'gallery']);
    $projects = lynx()->pages->new('project', '/');
    $projects->name = 'projects';
    $projects->title = 'Projects';
    $projects->save();
    $documentary = lynx()->pages->new('project', '/projects/');
    $documentary->name = 'documentary';
    $documentary->title = 'Documentary';
    $documentary->save();

    app()->instance(HostResolver::class, new class implements HostResolver
    {
        public function addresses(string $host): array
        {
            return $host === 'example.com' ? ['93.184.216.34'] : [];
        }
    });
    Http::fake(['https://example.com/still.jpg' => Http::response(jpeg(800, 450), 200, ['Content-Type' => 'image/jpeg'])]);
});

it('runs the example that stores a downloaded image and adds it to the field, described', function (): void {
    require MEDIA_SNIPPETS.'/store-from-code.php';

    $gallery = lynx()->pages->get('/projects/documentary/')?->gallery;

    expect($gallery?->count())->toBe(1)
        ->and($gallery?->first()?->description())->toBe('On set');
});
