<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\Core\Pages\Visibility\VisibilityRule;
use LynxCms\Core\Support\Tables;
use LynxCms\PagePassword\Actions\EnablePagePassword;
use LynxCms\PagePassword\PagePasswordSchema;
use LynxCms\StaticSite\Admin\Livewire\StaticExport;

/*
 * An export holds what a guest sees, also when an editor starts it from the admin: the editor's
 * session and sign-in never reach the pages it renders, and are still theirs afterwards.
 */

beforeEach(function (): void {
    $this->dist = testingPath('dist-guest');
    File::deleteDirectory($this->dist);
    app()->usePublicPath(testingPath('public'));
    config(['lynx-static.path' => $this->dist, 'lynx-static.url' => 'https://static.example']);
    courseSite();
    createCourses('open', 'members');
    $this->actingAs(new GenericUser(['id' => 1]));
});

afterEach(function (): void {
    File::deleteDirectory($this->dist);
    File::delete($this->dist.'.zip');
});

it('leaves out pages a visibility rule keeps for signed in members', function (): void {
    $membersId = (int) lynx()->pages->get('/courses/members/')?->id;
    app(PageVisibility::class)->add(new readonly class($membersId) implements VisibilityRule
    {
        public function __construct(private int $membersId) {}

        public function apply(Builder $query): void
        {
            if (! auth()->check()) {
                $query->where(Tables::PAGES.'.id', '!=', $this->membersId);
            }
        }

        public function allows(Page $page): bool
        {
            return $page->id !== $this->membersId || auth()->check();
        }
    });

    Livewire::test(StaticExport::class)->call('export', false)->assertSee('Exported');

    expect(File::exists($this->dist.'/courses/open/index.html'))->toBeTrue()
        ->and(File::exists($this->dist.'/courses/members/index.html'))->toBeFalse();
});

it('leaves out a password page, which a static copy cannot unlock', function (): void {
    lynx()->run(EnablePagePassword::class, ['template' => 'course']);
    $members = lynx()->pages->get('/courses/members/');
    $members->{PagePasswordSchema::FIELD} = 'open sesame';
    $members->summary = 'Only for members';
    $members->save();

    Livewire::test(StaticExport::class)->call('export', false)->assertSee('Exported');

    expect(File::exists($this->dist.'/courses/open/index.html'))->toBeTrue()
        ->and(File::exists($this->dist.'/courses/members/index.html'))->toBeFalse()
        ->and(collect(File::allFiles($this->dist))->contains(fn (SplFileInfo $file): bool => str_contains($file->getContents(), 'Only for members')))->toBeFalse();
});

it('keeps the editor signed in, with their session, after the export', function (): void {
    session(['editor_note' => 'kept']);

    Livewire::test(StaticExport::class)->call('export', false)->assertSee('Exported');

    expect(auth()->id())->toBe(1)
        ->and(session('editor_note'))->toBe('kept');
    Livewire::test(StaticExport::class)->assertOk();
});
