<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin\Livewire\Concerns;

use LynxCms\Forms\Models\Submission;

/**
 * The sidebar shows how many submissions are new, drawn with the page. A screen that changes a
 * status sends the new count, so the sidebar follows without a reload.
 *
 * @internal
 */
trait RefreshesUnreadCount
{
    private function refreshUnreadCount(): void
    {
        $this->dispatch('lynx-nav-badge', route: 'lynx.admin.forms', value: Submission::query()->where('status', 'new')->count());
    }
}
