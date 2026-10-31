@props(['node', 'state'])
@php
    $id = $node->id;
    $button = \LynxCms\Admin\Tree\TreeUi::button('ghost', 'xs');
    $icon = \LynxCms\Admin\Tree\TreeUi::class;
@endphp
{{-- The row's actions as small text, ProcessWire style, right after the title: the main ones, then the rest behind
     "More". Shown while the row is selected, and on hover or keyboard focus through admin.css
     (.lynx-tree-actions), so no per-row state is needed. Plain buttons with BlatUI's classes: every
     row has this bar, so components here are slow (TreeUi). --}}
<span x-show="selected === {{ $id }}" x-cloak x-data="{ more: false }" :class="more && 'is-open'" class="lynx-tree-actions flex flex-wrap items-center gap-0.5">
    @if ($node->isTrash())
        @if ($node->can('delete'))
            {{-- Empty trash after a second click; what stays (locked pages) is reported above the tree. --}}
            <span x-data="{ confirming: false }" class="flex items-center gap-0.5">
                <button type="button" class="{{ $button }}" x-show="! confirming" @click="confirming = true"><span>{{ __('lynx-admin::pages.actions.empty_trash') }}</span></button>
                <span x-show="confirming" x-cloak class="flex items-center gap-1 text-sm">
                    <span class="text-destructive">{{ __('lynx-admin::pages.actions.confirm_empty_trash') }}</span>
                    <button type="button" class="{{ \LynxCms\Admin\Tree\TreeUi::button('ghost', 'xs', 'text-destructive hover:text-destructive') }}" wire:click="emptyTrash" @click="confirming = false">{{ __('lynx-admin::pages.actions.yes_empty') }}</button>
                    <button type="button" class="{{ $button }}" @click="confirming = false">{{ __('lynx-admin::pages.tree.cancel') }}</button>
                </span>
            </span>
        @endif
    @elseif ($state['events'] !== null)
        <button type="button" class="{{ $button }}" @click="$dispatch('{{ $state['events'] }}-edit', { id: {{ $id }} })"><span>{{ __('lynx-admin::pages.actions.edit') }}</span></button>
    @elseif (Route::has('lynx.admin.pages.edit') && $node->can('edit'))
        <a href="{{ route('lynx.admin.pages.edit', $id) }}" class="{{ $button }}"><span>{{ __('lynx-admin::pages.actions.edit') }}</span></a>
    @endif
    @if ($node->isTrashed() && $node->can('delete'))
        <button type="button" class="{{ $button }}" wire:click="restore({{ $id }})"><span>{{ __('lynx-admin::pages.actions.restore') }}</span></button>
    @endif
    @if ($node->hasUrl() && ! $node->isInTrash())
        <a href="{{ $node->url }}" target="_blank" rel="noopener" class="{{ $button }}"><span>{{ __('lynx-admin::pages.actions.view') }}</span></a>
    @endif
    {{-- No New where the template allows no children (ADR-0032). --}}
    @if ($node->allowsChildren && $state['events'] !== null)
        <button type="button" class="{{ $button }}" @click="$dispatch('{{ $state['events'] }}-new', { parent: {{ $id }} })"><span>{{ __('lynx-admin::pages.actions.new') }}</span></button>
    @elseif ($node->allowsChildren && Route::has('lynx.admin.pages.create') && $node->can('create') && $node->place === \LynxCms\Admin\Tree\TreeNode::SITE)
        <a href="{{ route('lynx.admin.pages.create', ['parent' => $id]) }}" class="{{ $button }}"><span>{{ __('lynx-admin::pages.actions.new') }}</span></a>
    @endif
    @if ($node->canMove() && $state['sortable'])
        <button type="button" class="{{ $button }}" wire:click="startMove({{ $id }})"><span>{{ __('lynx-admin::pages.actions.move') }}</span></button>
    @endif
    @if ($node->detail !== '')
        {{-- BlatUI's outline badge. --}}
        <span class="mx-1 inline-flex items-center rounded-md border px-2 py-0.5 text-xs whitespace-nowrap text-muted-foreground">{{ $node->detail }}</span>
    @endif

    @if ($node->canChangeStatus() || $node->canDelete())
        <button type="button" class="{{ \LynxCms\Admin\Tree\TreeUi::button('ghost', 'icon-xs') }}" @click="more = ! more" :aria-expanded="more" aria-label="{{ __('lynx-admin::pages.actions.more') }}">{{ $icon::icon('ellipsis') }}</button>
        <span x-show="more" x-cloak class="flex flex-wrap items-center gap-0.5 border-s ps-1">
            @if ($node->canChangeStatus())
                {{-- A locked page only lets the lock change (ADR-0027). --}}
                @unless ($node->isLocked())
                    @if ($node->can('publish'))
                        <button type="button" class="{{ $button }}" wire:click="toggleStatus({{ $id }}, 'unpublished')">{{ __($node->isUnpublished() ? 'lynx-admin::pages.actions.publish' : 'lynx-admin::pages.actions.unpublish') }}</button>
                    @endif
                    @if ($node->can('status'))
                        <button type="button" class="{{ $button }}" wire:click="toggleStatus({{ $id }}, 'hidden')">{{ __($node->isHidden() ? 'lynx-admin::pages.actions.unhide' : 'lynx-admin::pages.actions.hide') }}</button>
                    @endif
                @endunless
                @if ($node->can('status'))
                    <button type="button" class="{{ $button }}" wire:click="toggleStatus({{ $id }}, 'locked')">{{ __($node->isLocked() ? 'lynx-admin::pages.actions.unlock' : 'lynx-admin::pages.actions.lock') }}</button>
                @endif
            @endif
            @if ($node->canDelete())
                <button type="button" class="{{ \LynxCms\Admin\Tree\TreeUi::button('ghost', 'xs', 'text-destructive hover:text-destructive') }}" wire:click="confirmDelete({{ $id }})">{{ __($node->isTrashed() ? 'lynx-admin::pages.actions.delete_for_good' : ($state['trash'] ? 'lynx-admin::pages.actions.trash' : 'lynx-admin::pages.actions.delete')) }}</button>
            @endif
        </span>
    @endif
</span>
