<?php

declare(strict_types=1);

use Database\Seeders\AtlasEditorsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Pages\RunSchedules;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

/*
 * Chapter 4 of the training site guide: page editor layouts, roles for the school's staff, drafts
 * and scheduled publishing.
 */

require_once __DIR__.'/training.php';
require_once TRAINING.'/database/seeders/AtlasEditorsSeeder.php';

beforeEach(function (): void {
    trainingSite();
    new AtlasEditorsSeeder()->run();
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['course-manager']]);
    lynx()->run(AssignRoles::class, ['user' => '3', 'roles' => ['news-writer']]);
});

function trainingPage(string $path): Page
{
    $page = lynx()->pages->get($path);
    assert($page instanceof Page);

    return $page;
}

it('lays out the course editor with a Teaching tab and a facts sidebar', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(PageEditor::class, ['pageId' => trainingPage('/courses/yoga-teacher-training/')->id])
        ->assertSeeInOrder(['Content', 'Teaching', 'Settings'])
        ->assertSee('Facts')
        ->assertSee('Shown on course cards and in search results.');
});

it('lets the course manager run the catalogue and nothing else', function (): void {
    lynx()->actingAs(Actor::user(2), function (): void {
        $course = trainingPage('/courses/sports-nutrition/');
        $course->price = 450;
        $course->save();

        $session = lynx()->pages->new('session', '/courses/sports-nutrition/');
        $session->name = 'extra-date';
        $session->fill('en', ['title' => 'Extra date', 'starts' => now()->addMonths(3)->format('Y-m-d H:i'), 'location' => trainingPage('/locations/lisbon/')->id, 'seats' => 12]);
        $session->save();

        $about = trainingPage('/about/');
        $about->title = 'Not yours';

        expect(fn (): mixed => $about->save())->toThrow(AuthorizationException::class)
            ->and(fn () => lynx()->pages->new('post', '/posts/')->fill('en', ['title' => 'Nope'])->save())->toThrow(AuthorizationException::class);
    });

    expect((float) (string) trainingPage('/courses/sports-nutrition/')->price)->toBe(450.0)
        ->and(lynx()->pages->get('/courses/sports-nutrition/extra-date/'))->not->toBeNull();
});

it('keeps a draft out of view until it is published', function (): void {
    lynx()->actingAs(Actor::user(3), fn () => lynx()->pages->saveDraft('/posts/new-porto-studio/', ['summary' => ['en' => 'Classes start on Monday.']]));

    $this->withoutVite();
    $this->get('/posts/new-porto-studio')->assertOk()->assertSee('Classes start next month')->assertDontSee('Classes start on Monday');
    $this->actingAs(new GenericUser(['id' => 3]))->get('/posts/new-porto-studio?preview=draft')->assertSee('Classes start on Monday');
});

it('publishes a news post at the time the writer chose', function (): void {
    $post = lynx()->pages->new('post', '/posts/');
    $post->name = 'summer-intensive';
    $post->title = 'Summer intensive';
    $post->setStatus(PageStatus::UNPUBLISHED);
    $post->save();

    lynx()->pages->schedule('/posts/summer-intensive/', '*', publishAt: now()->addHour());
    expect(lynx()->pages->count('template=post, name=summer-intensive'))->toBe(0);

    $this->travel(2)->hours();
    lynx()->run(RunSchedules::class);

    expect(lynx()->pages->count('template=post, name=summer-intensive'))->toBe(1);
});

it('publishes the draft when the writer is ready', function (): void {
    lynx()->actingAs(Actor::user(3), function (): void {
        lynx()->pages->saveDraft('/posts/new-porto-studio/', ['summary' => ['en' => 'Classes start on Monday.']]);
        lynx()->pages->publishDraft('/posts/new-porto-studio/');
    });

    expect((string) trainingPage('/posts/new-porto-studio/')->summary)->toBe('Classes start on Monday.');
});
