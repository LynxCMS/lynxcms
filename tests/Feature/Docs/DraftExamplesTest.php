<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use LynxCms\Core\Schema\Draft\DraftPaths;

/*
 * "A draft in two languages" in Templates and Fields shows docs/snippets/drafts/bilingual-blog.yaml.
 * It adds its languages, so it builds on a new site and on one that has Portuguese already.
 */

const BILINGUAL_DRAFT = __DIR__.'/../../../docs/snippets/drafts/bilingual-blog.yaml';

beforeEach(function (): void {
    installLynx();
    $this->dir = testingPath('draft-examples');
    File::deleteDirectory($this->dir);
    File::ensureDirectoryExists($this->dir.'/views');
    config(['view.paths' => [$this->dir.'/views']]);
    app('view')->getFinder()->setPaths([$this->dir.'/views']);
    app()->instance(DraftPaths::class, new DraftPaths($this->dir.'/schema', $this->dir.'/types.php', $this->dir.'/ide.php'));
});

afterEach(fn (): bool => File::deleteDirectory($this->dir));

it('builds the bilingual blog on a new site', function (): void {
    $this->artisan('lynx:draft', ['path' => BILINGUAL_DRAFT])->assertSuccessful();

    expect(lynx()->templates->find('post')?->labels()->toStorage())->toBe(['en' => 'Post', 'pt' => 'Artigo'])
        ->and(lynx()->fields->choices('category')->values())->toBe(['news', 'guides'])
        ->and(lynx()->language('pt', fn () => lynx()->fields->choices('category')->labels()))->toContain('Notícias')
        ->and(lynx()->languages->default()->code)->toBe('en');
});

it('builds the bilingual blog on a site that has Portuguese already', function (): void {
    lynx()->languages->create('pt', 'Português', locale: 'pt-PT', fallback: 'default');

    $this->artisan('lynx:draft', ['path' => BILINGUAL_DRAFT])->assertSuccessful();

    expect(lynx()->languages->find('pt')?->fallback_id)->toBe(lynx()->languages->default()->id);
});
