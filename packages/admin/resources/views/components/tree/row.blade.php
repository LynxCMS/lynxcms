@props(['node', 'depth', 'parentPosition', 'state'])
@php
    $id = $node->id;
    $isMoving = $state['moving'] === $id;
    $isDeleting = $state['deleting'] === $id;
@endphp
<div
    @class([
        'lynx-tree-row flex min-h-11 flex-wrap items-center gap-x-1.5 gap-y-1',
        'lynx-tree-row-moving' => $isMoving,
    ])
    style="--depth: {{ $depth }}"
    @if ($isMoving)
        {{-- A key that changes with the position re-creates the row after each move, so x-init refocuses it. --}}
        wire:key="moving-{{ $id }}-{{ $node->parentId }}-{{ $node->position }}"
        tabindex="0"
        x-init="$nextTick(() => $el.focus())"
        @keydown.arrow-up.prevent="$wire.moveUp({{ $id }}, {{ $node->parentId }}, {{ $node->position }})"
        @keydown.arrow-down.prevent="$wire.moveDown({{ $id }}, {{ $node->parentId }}, {{ $node->position }})"
        @keydown.arrow-right.prevent="$wire.indent({{ $id }}, {{ $node->parentId }}, {{ $node->position }})"
        @keydown.arrow-left.prevent="$wire.outdent({{ $id }}, {{ $node->parentId }}, {{ $parentPosition }})"
        @keydown.enter.prevent="$wire.cancelMove()"
        aria-label="Moving {{ $node->title }}"
    @endif
>
    @if ($isMoving)
        <span class="lynx-tree-handle flex size-6 shrink-0 cursor-grab touch-none items-center justify-center rounded-md text-muted-foreground hover:text-foreground active:cursor-grabbing" wire:sort:handle title="Drag to move" aria-hidden="true">
            {{ \LynxCms\Admin\Tree\TreeUi::icon('grip-vertical', 'size-4') }}
        </span>
    @endif

    {{-- Chevron as in BlatUI's server tree table; it turns when the branch is open (admin.css). --}}
    @if ($node->hasChildren())
        <button type="button" tabindex="-1" wire:click="toggle({{ $id }})" class="flex size-6 shrink-0 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground" aria-label="Show or hide the pages under {{ $node->title }}">
            {{ \LynxCms\Admin\Tree\TreeUi::icon('chevron-right', 'lynx-tree-chevron size-4 transition-transform duration-150 rtl:-scale-x-100') }}
        </button>
    @else
        <span class="size-6 shrink-0" aria-hidden="true"></span>
    @endif

    <span class="inline-flex min-w-0 items-center gap-2">
        @if ($node->isTrash())
            {{ \LynxCms\Admin\Tree\TreeUi::icon('trash-2', 'size-4 shrink-0 text-muted-foreground') }}
        @else
            <x-lynx-admin::tree.icon :folder="$node->hasChildren()" />
        @endif
        <button
            type="button"
            @class([
                'lynx-tree-title -mx-1.5 truncate rounded-md px-1.5 py-0.5 text-start outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                'line-through decoration-1' => $node->isUnpublished() && ! $node->isTrash(),
                'lynx-tree-hidden' => $node->isHidden(),
                'text-muted-foreground' => $node->isSystem(),
            ])
            :class="selected === {{ $id }} && 'bg-highlight'"
            @click="selected = selected === {{ $id }} ? null : {{ $id }}"
            @if ($node->hasChildren()) wire:click="toggle({{ $id }})" @endif
        >{{ $node->title }}</button>
        @if ($node->isLocked())
            <x-lynx-admin::tree.lock />
        @endif
        @foreach ($node->markers as $marker)
            <span class="text-muted-foreground inline-flex shrink-0" data-tree-marker="{{ $marker['key'] }}" title="{{ $marker['label'] }}">
                <x-lynx-admin::ui.icon :name="$marker['icon']" class="size-3.5" aria-hidden="true" />
                <span class="sr-only">{{ $marker['label'] }}</span>
            </span>
        @endforeach
        @if ($node->hasDraft)
            {{-- Unpublished changes waiting in a draft (ADR-0039). --}}
            <span class="inline-flex shrink-0 items-center rounded-md border border-dashed px-1.5 py-px text-[0.625rem] font-medium text-muted-foreground" data-has-draft title="Has a draft">draft</span>
        @endif
        @if ($node->hasChildren())
            <span class="{{ \LynxCms\Admin\Tree\TreeUi::COUNT_BADGE }}" data-child-count="{{ $node->childCount }}">{{ $node->childCount }}</span>
        @endif
    </span>

    @if ($isDeleting)
        <span role="alert" class="flex flex-wrap items-center gap-2 text-sm">
            @php($below = $state['deleteCount'] > 0 ? ' and '.$state['deleteCount'].' '.Str::plural('page', $state['deleteCount']).' below' : '')
            @if ($state['trash'] && ! $node->isTrashed())
                <span>Move “{{ $node->title }}”{{ $below }} to the trash? It can be restored from there.</span>
                <x-lynx-admin::ui.button variant="outline" size="xs" wire:click="delete({{ $id }})">Move to trash</x-lynx-admin::ui.button>
            @else
                <span class="text-destructive">Delete “{{ $node->title }}”{{ $below }} for good? This cannot be undone.</span>
                <x-lynx-admin::ui.button variant="destructive" size="xs" wire:click="delete({{ $id }})">Yes, delete</x-lynx-admin::ui.button>
            @endif
            <x-lynx-admin::ui.button variant="ghost" size="xs" wire:click="cancelDelete">Cancel</x-lynx-admin::ui.button>
        </span>
    @else
        <x-lynx-admin::tree.actions :node="$node" :state="$state" />
    @endif
</div>
