@props(['level', 'depth', 'parentPosition', 'state'])
@if ($level->hasPager())
    <x-lynx-admin::tree.pager :level="$level" :depth="$depth" :state="$state" />
@endif
<ul
    role="group"
    @if ($state['sortable'] && $level->isComplete)
        wire:sort="handleSort"
        wire:sort:group="lynx-tree"
        wire:sort:group-id="{{ $level->parentId }}"
        wire:sort:config="{{ \LynxCms\Admin\Livewire\PageTree::SORTABLE_CONFIG }}"
    @endif
>
    @foreach ($level->branches as $child)
        <x-lynx-admin::tree.branch
            :branch="$child"
            :depth="$depth"
            :parent-position="$parentPosition"
            :state="$state"
        />
    @endforeach
</ul>
