<?php

declare(strict_types=1);

use App\Mail\NewPostMail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Events\PageSaved;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

// In a service provider's boot() method.

// #region publish-rule
// A rule for every way of saving: the admin, your code, imports, MCP and the AI agent.
lynx()->hook(SavePage::class)->after(function (Page $page): void {
    $isPublishedPost = $page->template()->name === 'post' && ! $page->hasStatus(PageStatus::UNPUBLISHED);

    if ($isPublishedPost && $page->summary === null) {
        // Rolls the whole save back. Keyed by the field, the page editor shows it on that field.
        throw ValidationException::withMessages(['values.summary' => 'A published post needs a summary.']);
    }
});
// #endregion publish-rule

// #region notify
// Runs once the save is committed, and never for a dry run.
Event::listen(PageSaved::class, function (PageSaved $event): void {
    $post = lynx()->pages->get($event->pageId);

    if ($event->created && $post?->template()->name === 'post') {
        Mail::to('editors@example.com')->send(new NewPostMail($post));
    }
});
// #endregion notify
