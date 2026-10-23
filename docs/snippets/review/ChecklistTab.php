<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Blog\PostChecks;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Core\Pages\Page;

// #region tab
/**
 * The page editor's Checklist tab on posts: what the post still needs, and a button that writes a
 * summary from the start of the body.
 */
final class ChecklistTab extends Component
{
    public int $pageId;

    /**
     * Livewire sends every click to its own endpoint, outside the admin's routes, so the
     * component checks access itself, on every request.
     */
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('blog.review'), 403);
    }

    /** A save like any other: the action checks pages.edit and logs the change. */
    public function summarizeFromBody(): void
    {
        $post = $this->post();
        $post->summary = Str::limit(trim(strip_tags((string) $post->body)), PostChecks::SUMMARY_LIMIT - 1, '…', preserveWords: true);
        $post->save();
    }

    public function render(): View
    {
        return view('blog.checklist-tab', ['problems' => PostChecks::problems($this->post())]);
    }

    private function post(): Page
    {
        $post = lynx()->pages->get($this->pageId);
        abort_unless($post instanceof Page && $post->template()->name === 'post', 404);

        return $post;
    }
}
// #endregion tab
