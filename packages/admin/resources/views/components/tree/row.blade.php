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
        aria-label="{{ __('lynx-admin::pages.tree.moving', ['page' => $node->title]) }}"
    @endif
>
    @if ($isMoving)
        <span class="lynx-tree-handle flex size-6 shrink-0 cursor-grab touch-none items-center justify-center rounded-md text-muted-foreground hover:text-foreground active:cursor-grabbing" wire:sort:handle title="{{ __('lynx-admin::pages.tree.drag_to_move') }}" aria-hidden="true">
            {{ \LynxCms\Admin\Tree\TreeUi::icon('grip-vertical', 'size-4') }}
        </span>
    @endif

    {{-- Chevron as in BlatUI's server tree table; it turns when the branch is open (admin.css). --}}
    @if ($node->hasChildren())
        <button type="button" tabindex="-1" wire:click="toggle({{ $id }})" class="flex size-6 shrink-0 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground" aria-label="{{ __('lynx-admin::pages.tree.toggle_children', ['page' => $node->title]) }}">
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
            <span class="inline-flex shrink-0 items-center rounded-md border border-dashed px-1.5 py-px text-[0.625rem] font-medium text-muted-foreground" data-has-draft title="{{ __('lynx-admin::pages.tree.has_draft') }}">{{ __('lynx-admin::pages.tree.draft') }}</span>
        @endif
        @if ($node->location !== null)
            <span class="text-muted-foreground truncate text-xs" data-tree-location>{{ __('lynx-admin::pages.tree.in_location', ['location' => $node->location]) }}</span>
        @endif
        @if ($node->hasChildren())
            <span class="{{ \LynxCms\Admin\Tree\TreeUi::COUNT_BADGE }}" data-child-count="{{ $node->childCount }}">{{ $node->childCount }}</span>
        @endif
    </span>

    @if ($isDeleting)
        <span role="alert" class="flex flex-wrap items-center gap-2 text-sm">
            @php($below = $state['deleteCount'] > 0 ? trans_choice('lynx-admin::pages.tree.below', $state['deleteCount']) : '')
            @if ($state['trash'] && ! $node->isTrashed())
                <span>{{ __('lynx-admin::pages.tree.confirm_trash', ['page' => $node->title, 'below' => $below]) }}</span>
                <x-lynx-admin::ui.button variant="outline" size="xs" wire:click="delete({{ $id }})">{{ __('lynx-admin::pages.tree.move_to_trash') }}</x-lynx-admin::ui.button>
            @else
                <span class="text-destructive">{{ __('lynx-admin::pages.tree.confirm_delete', ['page' => $node->title, 'below' => $below]) }}</span>
                <x-lynx-admin::ui.button variant="destructive" size="xs" wire:click="delete({{ $id }})">{{ __('lynx-admin::pages.tree.yes_delete') }}</x-lynx-admin::ui.button>
            @endif
            <x-lynx-admin::ui.button variant="ghost" size="xs" wire:click="cancelDelete">{{ __('lynx-admin::pages.tree.cancel') }}</x-lynx-admin::ui.button>
        </span>
    @else
        <x-lynx-admin::tree.actions :node="$node" :state="$state" />
    @endif

    @if ($node->details !== null)
        @php($details = $node->details)
        @php($states = ['own' => __('lynx-admin::pages.tree.language_own'), 'follows' => __('lynx-admin::pages.tree.language_follows'), 'missing' => __('lynx-admin::pages.tree.language_missing')])
        {{-- The Details view's columns (TreeDetails); the facets match the editor's language tabs. --}}
        <span class="ms-auto hidden items-center gap-4 text-xs md:flex">
            <span class="flex w-56 flex-wrap gap-x-3 gap-y-1 font-medium tracking-wide uppercase">
                @foreach ($details['languages'] as $language)
                    <span class="text-muted-foreground inline-flex items-center" data-language-state="{{ $language['state'] }}" title="{{ $language['label'] }}: {{ $states[$language['state']] }}">
                        {{ $language['label'] }}<span aria-hidden="true" @class(['lynx-tab-state', 'is-filled' => $language['state'] === 'own', 'is-fallback' => $language['state'] === 'follows'])></span>
                        <span class="sr-only">, {{ $states[$language['state']] }}</span>
                    </span>
                @endforeach
            </span>
            <span @class(['inline-flex w-28 items-center gap-1.5 text-sm', 'text-success' => $details['status'] === 'published', 'text-muted-foreground' => $details['status'] !== 'published'])>
                <span aria-hidden="true" @class(['size-1.5 rounded-full', 'bg-current' => $details['status'] !== 'hidden', 'border border-current' => $details['status'] === 'hidden'])></span>
                {{ __('lynx-admin::pages.status.'.$details['status']) }}
            </span>
            <span class="text-muted-foreground w-48 truncate text-sm" @if ($details['changedAt']) title="{{ $details['changedAt'] }}" @endif>
                {{ $details['changed'] ?? '' }}@if ($details['changedBy']), {{ $details['changedBy'] }}@endif
            </span>
        </span>
    @endif
</div>
