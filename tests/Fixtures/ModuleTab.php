<?php

declare(strict_types=1);

namespace LynxCms\Tests\Fixtures;

use Livewire\Component;

/** A module's page editor tab, for the EditorTabs tests. */
final class ModuleTab extends Component
{
    public int $pageId;

    public function render(): string
    {
        return '<div>Module tab for page {{ $pageId }}</div>';
    }
}
