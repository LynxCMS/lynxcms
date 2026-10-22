<?php

declare(strict_types=1);

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Livewire;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Media\Actions\StoreMedia;
use LynxCms\PagePassword\Actions\EnablePagePassword;
use LynxCms\PagePassword\PagePasswordSchema;

beforeEach(function (): void {
    View::addLocation(__DIR__.'/../../Fixtures/views');
    $this->dist = testingPath('dist');
    $this->public = testingPath('public');
    File::deleteDirectory($this->dist);
    File::deleteDirectory($this->public);
    File::ensureDirectoryExists($this->public.'/build');
    File::put($this->public.'/build/app.css', 'body{}');
    File::put($this->public.'/index.php', '<?php');
    File::put($this->public.'/.htaccess', 'RewriteEngine On');
    File::ensureDirectoryExists($this->public.'/lynx-cache/localhost');
    File::put($this->public.'/lynx-cache/localhost/index.html', 'cached');
    app()->usePublicPath($this->public);
    config(['lynx-static.path' => $this->dist, 'lynx-static.url' => 'https://static.example']);

    installLynx();
    lynx()->languages->add('pt', 'Português', fallback: 'default');
    lynx()->templates->add('note', fields: [['name' => 'title', 'required' => true]]);

    foreach (['a', 'b'] as $name) {
        $page = lynx()->pages->new('note', '/');
        $page->name = $name;
        $page->title = 'Note '.$name;
        $page->setLanguageName('pt', 'nota-'.$name);
        $page->save();
    }
});

afterEach(function (): void {
    File::deleteDirectory($this->dist);
    File::deleteDirectory($this->public);
});

it('exports every published page in every language as an index.html, linked to the static address', function (): void {
    $draft = lynx()->pages->new('note', '/');
    $draft->name = 'draft';
    $draft->title = 'Draft';
    $draft->setStatus(PageStatus::UNPUBLISHED);
    $draft->save();

    $this->artisan('lynx:static')->expectsOutputToContain('6 pages')->assertSuccessful();

    expect(File::get($this->dist.'/index.html'))->toContain('<!DOCTYPE html>')
        ->and(File::get($this->dist.'/a/index.html'))->toContain('Note a')
        ->and(File::get($this->dist.'/pt/nota-b/index.html'))->toContain('lang="pt')
        ->and(File::get($this->dist.'/a/index.html'))->toContain('https://static.example/pt/nota-a')
        ->and(File::get($this->dist.'/a/index.html'))->not->toContain('http://localhost')
        ->and(File::exists($this->dist.'/draft/index.html'))->toBeFalse()
        ->and(File::exists($this->dist.'/.lynx-static'))->toBeTrue();
});

it('leaves out pages a visitor may not see', function (): void {
    lynx()->run(EnablePagePassword::class, ['template' => 'note']);
    $locked = lynx()->pages->get('/b');
    $locked->{PagePasswordSchema::FIELD} = 'secret';
    $locked->save();

    $this->artisan('lynx:static')->assertSuccessful();

    expect(File::exists($this->dist.'/a/index.html'))->toBeTrue()
        ->and(File::exists($this->dist.'/b/index.html'))->toBeFalse()
        ->and(File::exists($this->dist.'/pt/nota-b/index.html'))->toBeFalse();
});

it('copies the public files, without PHP entry points or the page cache', function (): void {
    $this->artisan('lynx:static')->assertSuccessful();

    expect(File::get($this->dist.'/build/app.css'))->toBe('body{}')
        ->and(File::exists($this->dist.'/index.php'))->toBeFalse()
        ->and(File::exists($this->dist.'/.htaccess'))->toBeFalse()
        ->and(File::exists($this->dist.'/lynx-cache'))->toBeFalse();
});

it('writes the not found page, the sitemap and robots.txt for the static address', function (): void {
    $this->artisan('lynx:static')->assertSuccessful();

    expect(File::exists($this->dist.'/404.html'))->toBeTrue()
        ->and(File::get($this->dist.'/sitemap.xml'))->toContain('<loc>https://static.example/a</loc>')
        ->and(File::get($this->dist.'/robots.txt'))->toContain('https://static.example/sitemap.xml');
});

it('sends the old addresses of renamed pages on, with a redirects file and a page at each', function (): void {
    $page = lynx()->pages->get('/a');
    $page->name = 'renamed';
    $page->save();

    $this->artisan('lynx:static')->assertSuccessful();

    $page = File::get($this->dist.'/a/index.html');

    expect(File::get($this->dist.'/_redirects'))->toContain('/a /renamed 301')
        ->and($page)->toContain('<meta http-equiv="refresh" content="0; url=https://static.example/renamed">')
        ->and($page)->toContain('<link rel="canonical" href="https://static.example/renamed">')
        ->and(File::get($this->dist.'/renamed/index.html'))->toContain('Note a');
});

it('leaves the redirects out when turned off', function (): void {
    config(['lynx-static.redirects' => ['file' => false, 'pages' => false]]);
    $page = lynx()->pages->get('/a');
    $page->name = 'renamed';
    $page->save();

    $this->artisan('lynx:static')->assertSuccessful();

    expect(File::exists($this->dist.'/_redirects'))->toBeFalse()
        ->and(File::exists($this->dist.'/a/index.html'))->toBeFalse();
});

it('warns about pages that need PHP to work', function (): void {
    lynx()->templates->add('note-form', fields: [['name' => 'title', 'required' => true]]);
    $form = lynx()->pages->new('note-form', '/');
    $form->name = 'contact';
    $form->title = 'Contact';
    $form->save();

    $this->artisan('lynx:static')
        ->expectsOutputToContain('/contact: a form that posts to the site')
        ->assertSuccessful();

    expect(File::get($this->dist.'/contact/index.html'))->toContain('Contact');
});

it('replaces its own earlier export, and refuses a folder it did not make', function (): void {
    $this->artisan('lynx:static')->assertSuccessful();
    File::put($this->dist.'/stale.html', 'old');

    $this->artisan('lynx:static')->assertSuccessful();
    expect(File::exists($this->dist.'/stale.html'))->toBeFalse()
        ->and(File::exists($this->dist.'/a/index.html'))->toBeTrue();

    File::deleteDirectory($this->dist);
    File::ensureDirectoryExists($this->dist);
    File::put($this->dist.'/mine.txt', 'keep');

    $this->artisan('lynx:static')->expectsOutputToContain('was not made by a static export')->assertFailed();
    expect(File::get($this->dist.'/mine.txt'))->toBe('keep')
        ->and(File::glob(dirname($this->dist).'/'.basename($this->dist).'.lynx-*'))->toBe([]);
});

it('writes a zip of the export', function (): void {
    $this->artisan('lynx:static', ['--zip' => true])->assertSuccessful();

    $zip = new ZipArchive;
    $zip->open($this->dist.'.zip');

    expect($zip->locateName('a/index.html'))->not->toBeFalse()
        ->and($zip->locateName('build/app.css'))->not->toBeFalse()
        ->and($zip->locateName('.lynx-static'))->toBeFalse();

    $zip->close();
    File::delete($this->dist.'.zip');
})->skip(! class_exists(ZipArchive::class), 'needs ext-zip');

it('exports to the folder and address given on the command line', function (): void {
    $other = testingPath('dist-other');

    $this->artisan('lynx:static', ['--path' => $other, '--url' => 'https://other.example/'])->assertSuccessful();

    expect(File::get($other.'/a/index.html'))->toContain('https://other.example/pt/nota-a');
    File::deleteDirectory($other);
});

it('renders in the app\'s own environment when told to', function (): void {
    config(['lynx-static.environment' => null]);

    $this->artisan('lynx:static')->assertSuccessful();

    expect(File::get($this->dist.'/robots.txt'))->toContain('Disallow: /')
        ->and(app()->environment())->toBe('testing');
});

it('exports pages Livewire marks no-store for the back button, with a warning', function (): void {
    Livewire::component('cache-probe', new class extends Component
    {
        public function render(): string
        {
            return '<div>probe</div>';
        }
    });
    lynx()->templates->add('live-probe', fields: [['name' => 'title', 'required' => true]]);
    $page = lynx()->pages->new('live-probe', '/');
    $page->name = 'live';
    $page->title = 'Live';
    $page->save();

    $this->artisan('lynx:static')->expectsOutputToContain('/live: a Livewire component')->assertSuccessful();

    expect(File::exists($this->dist.'/live/index.html'))->toBeTrue();
});

it('writes scripts and styles that the site sends from a route', function (): void {
    Route::get('routed/app.js', fn (): ResponseFactory|Response => response('console.log(1)', 200, ['Content-Type' => 'text/javascript']));
    Route::get('routed/file.js', fn () => response()->file($this->public.'/build/app.css', ['Content-Type' => 'text/javascript']));
    Route::get('routed/stream.js', fn () => response()->stream(function (): void {
        echo 'streamed';
    }, 200, ['Content-Type' => 'text/javascript']));
    Route::getRoutes()->refreshNameLookups();
    lynx()->templates->add('assets-page', fields: [['name' => 'title', 'required' => true]]);
    $views = testingPath('views');
    File::ensureDirectoryExists($views.'/templates');
    File::put($views.'/templates/assets-page.blade.php', '<html><head><link rel="stylesheet" href="{{ asset(\'build/app.css\') }}"><script src="{{ url(\'routed/app.js\') }}?id=1"></script><script src="{{ url(\'routed/file.js\') }}"></script><script src="/routed/stream.js"></script><script src="https://cdn.example/x.js"></script></head><body>{{ $page->title }}</body></html>');
    View::addLocation($views);
    $page = lynx()->pages->new('assets-page', '/');
    $page->name = 'assets';
    $page->title = 'Assets';
    $page->save();

    $this->artisan('lynx:static')->assertSuccessful();

    expect(File::get($this->dist.'/routed/app.js'))->toBe('console.log(1)')
        ->and(File::get($this->dist.'/routed/file.js'))->toBe('body{}')
        ->and(File::get($this->dist.'/routed/stream.js'))->toBe('streamed')
        ->and(File::get($this->dist.'/build/app.css'))->toBe('body{}');
    File::deleteDirectory($views);
});

it('writes media at the static address, with the image sizes pages made while rendering', function (): void {
    $disk = testingPath('disk');
    File::deleteDirectory($disk);
    File::ensureDirectoryExists($disk);
    config(['filesystems.disks.public.root' => $disk, 'filesystems.disks.public.url' => 'http://localhost/storage']);
    Storage::forgetDisk('public');
    symlink($disk, $this->public.'/storage');
    lynx()->fields->add('photo', 'images', settings: ['max_files' => 1]);
    lynx()->templates->add('photo-page', fields: [['name' => 'title', 'required' => true], ['name' => 'photo']]);
    $views = testingPath('views');
    File::ensureDirectoryExists($views.'/templates');
    File::put($views.'/templates/photo-page.blade.php', '<html><body><img src="{{ $page->photo?->size(40, 30)->url() }}"></body></html>');
    View::addLocation($views);
    $page = lynx()->pages->new('photo-page', '/');
    $page->name = 'photo';
    $page->title = 'Photo';
    $page->save();
    $image = imagecreatetruecolor(80, 60);
    ob_start();
    imagejpeg($image);
    $entry = lynx()->run(StoreMedia::class, ['page' => (string) $page->id, 'field' => 'photo', 'base64' => base64_encode((string) ob_get_clean()), 'filename' => 'shot.jpg'])->value;
    $page->photo = [['values' => $entry]];
    $page->save();

    $this->artisan('lynx:static')->assertSuccessful();

    preg_match('#src="https://static\.example/storage/(lynx/[^"]+\.40x30\.jpg)"#', File::get($this->dist.'/photo/index.html'), $match);
    expect($match)->toHaveCount(2)
        ->and(File::exists($this->dist.'/storage/'.$match[1]))->toBeTrue()
        ->and(Storage::disk('public')->url('x.jpg'))->toBe('http://localhost/storage/x.jpg');
    File::deleteDirectory($views);
    File::deleteDirectory($disk);
});
