@props(['branch', 'depth', 'parentPosition', 'state'])
@php
    $node = $branch->node;
    $isDropTarget = $state['sortable'] && $state['moving'] !== null && $state['moving'] !== $node->id;
@endphp
<li
    wire:key="page-{{ $node->id }}"
    @unless ($node->isRoot()) wire:sort:item="{{ $node->id }}" @endunless
    role="treeitem"
    @if ($node->hasChildren()) aria-expanded="{{ $branch->isExpanded() ? 'true' : 'false' }}" @endif
    :aria-selected="selected === {{ $node->id }} ? 'true' : 'false'"
    data-page-id="{{ $node->id }}"
>
    <x-lynx-admin::tree.row
        :node="$node"
        :depth="$depth"
        :parent-position="$parentPosition"
        :state="$state"
    />

    @if ($branch->children)
        <x-lynx-admin::tree.level
            :level="$branch->children"
            :depth="$depth + 1"
            :parent-position="$node->position"
            :state="$state"
        />
    @elseif ($isDropTarget)
        <ul
            role="group"
            aria-label="Inside {{ $node->title }}"
            class="lynx-tree-dropzone"
            style="--depth: {{ $depth }}"
            wire:sort="handleSort"
            wire:sort:group="lynx-tree"
            wire:sort:group-id="{{ $node->id }}"
            wire:sort:config="{{ \LynxCms\Admin\Livewire\PageTree::SORTABLE_CONFIG }}"
        ></ul>
    @endif
</li>
