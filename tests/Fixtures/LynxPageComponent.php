<?php

declare(strict_types=1);

namespace LynxCms\Tests\Fixtures;

use Livewire\Component;
use LynxCms\Core\Livewire\UsesLynxPage;

/** An app component on a Lynx page, for the UsesLynxPage tests. */
final class LynxPageComponent extends Component
{
    use UsesLynxPage;

    public function render(): string
    {
        return '<div>Title: {{ $this->page()?->title }}</div>';
    }
}
