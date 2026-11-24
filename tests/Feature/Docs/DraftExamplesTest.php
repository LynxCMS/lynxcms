<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use LynxCms\Core\Schema\Draft\DraftPaths;

/*
 * "A draft in two languages" in Templates and Fields shows docs/snippets/drafts/bilingual-blog.yaml.
 * It builds on a site in English and Portuguese, and a site without them is told which is missing.
 */

const BILINGUAL_DRAFT = __DIR__.'/../../../docs/snippets/drafts/bilingual-blog.yaml';

beforeEach(function (): void {
    courseSite();
    $this->dir = testingPath('draft-examples');
    File::deleteDirectory($this->dir);
    File::ensureDirectoryExists($this->dir.'/views');
    config(['view.paths' => [$this->dir.'/views']]);
    app('view')->getFinder()->setPaths([$this->dir.'/views']);
    app()->instance(DraftPaths::class, new DraftPaths($this->dir.'/schema', $this->dir.'/types.php', $this->dir.'/ide.php'));
});

afterEach(fn (): bool => File::deleteDirectory($this->dir));

it('builds the bilingual blog on a site in English and Portuguese', function (): void {
    lynx()->languages->update('default', ['code' => 'en', 'name' => 'English']);

    $this->artisan('lynx:draft', ['path' => BILINGUAL_DRAFT])->assertSuccessful();

    expect(lynx()->templates->find('post')?->labels()->toStorage())->toBe(['en' => 'Post', 'pt' => 'Artigo'])
        ->and(lynx()->fields->choices('category')->values())->toBe(['news', 'guides'])
        ->and(lynx()->language('pt', fn () => lynx()->fields->choices('category')->labels()))->toContain('Notícias');
});

it('refuses the bilingual blog on a site without its languages, naming the missing one', function (): void {
    $this->artisan('lynx:draft', ['path' => BILINGUAL_DRAFT, '--dry-run' => true])
        ->expectsOutputToContain('[en]')
        ->assertFailed();
});
