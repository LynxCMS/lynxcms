<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Modelable;
use Livewire\Component;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Icons\IconSet;

/**
 * Chooses an icon: the chosen one with its name, and a searchable grid of previews. Bound with
 * wire:model, like an input; only names IconSet has are accepted.
 *
 * @internal
 */
final class IconPicker extends Component
{
    /** Icons shown at once; the search narrows the rest. */
    private const int SHOWN = 60;

    #[Modelable]
    public string $value = '';

    public string $search = '';

    public bool $disabled = false;

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
    }

    public function choose(string $name): void
    {
        if (! $this->disabled && app(IconSet::class)->has($name)) {
            $this->value = $name;
            $this->search = '';
        }
    }

    public function clear(): void
    {
        if (! $this->disabled) {
            $this->value = '';
        }
    }

    public function render(): View
    {
        return view('lynx-admin::livewire.icon-picker', ['icons' => app(IconSet::class)->search($this->search, self::SHOWN)]);
    }
}
