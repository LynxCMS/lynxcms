<?php

declare(strict_types=1);

namespace App\Providers;

use App\Blog\PostChecks;
use App\Blog\ReviewQueueTool;
use App\Livewire\ChecklistTab;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use LynxCms\Admin\Admin;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\EditorTabs;
use LynxCms\Admin\Navigation;
use LynxCms\Core\Agents\ReadToolRegistry;
use LynxCms\Core\Pages\Page;

/**
 * Blog review: a permission, an admin screen with a badge, a Checklist tab on posts and an MCP tool.
 */
final class BlogReviewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // #region permission
        lynx()->permissions()->register('blog.review', 'Review blog posts', requires: ['pages.view']);
        // #endregion permission

        // #region tool
        app(ReadToolRegistry::class)->register(ReviewQueueTool::class);
        // #endregion tool

        // The admin is optional: a site without it still gets the permission and the tool.
        if (class_exists(Admin::class)) {
            $this->registerAdmin();
        }
    }

    private function registerAdmin(): void
    {
        // #region admin
        $canReview = fn (): bool => app(AdminAccess::class)->allows('blog.review');

        Admin::routes(fn () => Admin::screen('blog/review', 'blog.review', 'blog.review')->name('blog.review'));

        // The badge runs on every admin page: cache the count once the blog has thousands of posts.
        app(Navigation::class)->add('Review', 'lynx.admin.blog.review', order: 20, icon: 'clipboard-check',
            visible: $canReview, badge: fn (): int => count(PostChecks::queue()));

        Livewire::component('blog.checklist-tab', ChecklistTab::class);
        app(EditorTabs::class)->add('checklist', 'Checklist', 'blog.checklist-tab',
            fn (Page $page): bool => $page->template()->name === 'post' && $canReview());
        // #endregion admin
    }
}
