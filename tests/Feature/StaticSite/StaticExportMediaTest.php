<?php

declare(strict_types=1);

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\File;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\Core\Pages\Visibility\VisibilityRule;
use LynxCms\StaticSite\Actions\ExportStaticSite;

/*
 * An export is for public hosting, so it carries the media of pages a guest could open and
 * nothing else from the media disk: not unpublished pages', trashed pages' or members' files.
 */

beforeEach(function (): void {
    $this->dist = testingPath('dist-media');
    $this->public = testingPath('public-media');
    File::deleteDirectory($this->dist);
    File::deleteDirectory($this->public);
    app()->usePublicPath($this->public);
    config(['lynx-static.path' => $this->dist, 'lynx-static.url' => 'https://static.example']);
    courseSite();
    $this->pages = createCourses('open', 'soon', 'old', 'members');
    lynx()->pages->setStatus($this->pages['soon'], add: ['unpublished']);
    lynx()->pages->trash($this->pages['old']);

    foreach ($this->pages as $name => $page) {
        File::ensureDirectoryExists($this->public.'/storage/lynx/'.$page->id);
        File::put($this->public.'/storage/lynx/'.$page->id.'/'.$name.'.pdf', '%PDF '.$name);
    }

    File::ensureDirectoryExists($this->public.'/css');
    File::put($this->public.'/css/app.css', 'body {}');
});

afterEach(function (): void {
    File::deleteDirectory($this->dist);
    File::deleteDirectory($this->public);
});

it('copies only the media of pages a guest could open', function (): void {
    $membersId = (int) $this->pages['members']->id;
    app(PageVisibility::class)->add(new readonly class($membersId) implements VisibilityRule
    {
        public function __construct(private int $membersId) {}

        public function apply(Builder $query): void {}

        public function allows(Page $page): bool
        {
            return $page->id !== $this->membersId || auth()->check();
        }
    });

    lynx()->run(ExportStaticSite::class);

    $exported = fn (string $name): bool => File::exists($this->dist.'/storage/lynx/'.$this->pages[$name]->id.'/'.$name.'.pdf');

    expect($exported('open'))->toBeTrue()
        ->and($exported('soon'))->toBeFalse()
        ->and($exported('old'))->toBeFalse()
        ->and($exported('members'))->toBeFalse()
        ->and(File::exists($this->dist.'/css/app.css'))->toBeTrue();
});
