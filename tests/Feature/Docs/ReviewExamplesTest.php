<?php

declare(strict_types=1);

use App\Blog\PostChecks;
use App\Blog\ReviewQueueTool;
use App\Livewire\ChecklistTab;
use App\Providers\BlogReviewServiceProvider;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Activity\ActivityEvent;
use LynxCms\Core\Pages\Page;
use LynxCms\Mcp\LynxServer;
use LynxCms\Mcp\Tools\ModuleReadTool;

/*
 * The blog review example of "Extending the admin", "Roles and permissions" and "AI": the files in
 * docs/snippets/review, on the blog of "Your First Site", with a draft post that needs work.
 */

const REVIEW = __DIR__.'/../../../docs/snippets/review';

require_once REVIEW.'/PostChecks.php';
require_once REVIEW.'/ChecklistTab.php';
require_once REVIEW.'/ReviewQueueTool.php';
require_once REVIEW.'/BlogReviewServiceProvider.php';

beforeEach(function (): void {
    installLynx();
    require __DIR__.'/../../../docs/snippets/first-site/schema.php';
    require __DIR__.'/../../../docs/snippets/first-site/pages.php';

    $draft = lynx()->pages->new('post', '/blog');
    $draft->name = 'draft';
    $draft->title = 'A draft';
    $draft->body = '<p>'.str_repeat('Lynx keeps every language in rows. ', 8).'</p>';
    $draft->save();

    new BlogReviewServiceProvider(app())->boot();
    Route::getRoutes()->refreshNameLookups();
    View::prependLocation(REVIEW.'/views');
    View::getFinder()->flush();

    $grants = fn (string ...$permissions): array => array_map(fn (string $permission): array => ['permission' => $permission], $permissions);
    lynx()->run(SaveRole::class, ['name' => 'reviewer', 'grants' => $grants('admin.access', 'pages.view', 'pages.edit', 'blog.review')]);
    lynx()->run(SaveRole::class, ['name' => 'auditor', 'grants' => $grants('admin.access', 'pages.view', 'blog.review')]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['reviewer']]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['editor']]);
    lynx()->run(AssignRoles::class, ['user' => '3', 'roles' => ['auditor']]);
});

function reviewPost(string $path): Page
{
    $post = lynx()->pages->get($path);
    assert($post instanceof Page);

    return $post;
}

it('finds the posts that need work', function (): void {
    expect(array_map(fn (array $item): ?string => $item['post']->path, PostChecks::queue()))->toBe(['blog/draft'])
        ->and(PostChecks::problems(reviewPost('/blog/draft')))->toBe(['Add a summary.', 'Set a publication date.']);
});

it('shows the Review screen and its badge to reviewers only', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]))->get('/lynx/blog/review')
        ->assertOk()->assertSee('A draft')->assertSee('Add a summary.')->assertDontSee('Hello, world');
    $this->get('/lynx/pages')->assertSeeInOrder(['Review', '1']);

    $this->actingAs(new GenericUser(['id' => 2]));
    $this->get('/lynx/blog/review')->assertForbidden();
    $this->get('/lynx/pages')->assertDontSee('lynx/blog/review');
});

it('adds the Checklist tab to posts, for reviewers', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(PageEditor::class, ['pageId' => reviewPost('/blog/draft')->id])
        ->assertSeeInOrder(['Content', 'Checklist', 'Settings'])
        ->set('tab', 'checklist')
        ->assertSee('Set a publication date.');
    Livewire::test(PageEditor::class, ['pageId' => reviewPost('/blog')->id])->assertDontSee('Checklist');

    $this->actingAs(new GenericUser(['id' => 2]));
    Livewire::test(PageEditor::class, ['pageId' => reviewPost('/blog/draft')->id])->assertDontSee('Checklist');
});

it('writes a summary from the body through the save action', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(ChecklistTab::class, ['pageId' => reviewPost('/blog/draft')->id])->call('summarizeFromBody');

    $summary = (string) reviewPost('/blog/draft')->summary;
    expect($summary)->toStartWith('Lynx keeps every language in rows.')->toEndWith('…')
        ->and(mb_strlen($summary))->toBeLessThanOrEqual(PostChecks::SUMMARY_LIMIT)
        ->and(PostChecks::problems(reviewPost('/blog/draft')))->toBe(['Set a publication date.'])
        ->and(ActivityEvent::query()->where('action', 'pages.save')->latest('id')->first()?->actor_id)->toBe('1');
});

it('refuses the tab to users without blog.review, on every request', function (): void {
    $this->actingAs(new GenericUser(['id' => 2]));

    Livewire::test(ChecklistTab::class, ['pageId' => reviewPost('/blog/draft')->id])->assertForbidden();
});

it('lets the save action refuse a reviewer who may not edit pages', function (): void {
    $this->actingAs(new GenericUser(['id' => 3]));

    Livewire::test(ChecklistTab::class, ['pageId' => reviewPost('/blog/draft')->id])
        ->call('summarizeFromBody')
        ->assertForbidden();

    expect(reviewPost('/blog/draft')->summary)->toBeNull();
});

it('gives MCP clients the review queue, with the reviewer\'s permission', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
    LynxServer::tool(new ModuleReadTool(ReviewQueueTool::class))
        ->assertOk()->assertSee('A draft')->assertSee('Add a summary.')->assertDontSee('Hello, world');

    $this->actingAs(new GenericUser(['id' => 2]));
    LynxServer::tool(new ModuleReadTool(ReviewQueueTool::class))->assertHasErrors(['blog.review']);
});
