{{-- One child in the Children tab: select, drag, its status, and its own actions. --}}
@php
    $id = (int) $child->id;
    $title = (string) ($child->title ?: $child->name());
    $isUnpublished = $child->hasStatus(\LynxCms\Core\Pages\PageStatus::UNPUBLISHED);
    $isHidden = $child->hasStatus(\LynxCms\Core\Pages\PageStatus::HIDDEN);
    $grandchildren = $childCounts[$id] ?? 0;
    // System pages (the 404 page, settings pages) cannot be unpublished or trashed.
    $isSystem = $child->hasStatus(\LynxCms\Core\Pages\PageStatus::SYSTEM);
@endphp
<li wire:key="child-{{ $id }}" @if ($canReorder) wire:sort:item="{{ $id }}" @endif class="hover:bg-muted/40 flex flex-wrap items-center gap-3 px-5 py-2.5" data-child="{{ $id }}">
    <input type="checkbox" class="blat-checkbox" value="{{ $id }}" wire:model.live="selected" aria-label="{{ __('lynx-admin::children.select', ['title' => $title]) }}">
    @if ($canReorder)
        <span wire:sort:handle class="text-muted-foreground cursor-grab select-none active:cursor-grabbing" title="{{ __('lynx-admin::children.drag') }}" aria-hidden="true">⠿</span>
    @endif
    <div class="grid min-w-0 flex-1 gap-0.5">
        <span class="flex flex-wrap items-center gap-2">
            <a href="{{ route('lynx.admin.pages.edit', $id) }}" wire:navigate @class(['truncate font-medium hover:underline', 'text-muted-foreground' => $isUnpublished])>{{ $title }}</a>
            @if ($isUnpublished) <x-lynx-admin::ui.badge variant="outline">{{ __('lynx-admin::children.unpublished') }}</x-lynx-admin::ui.badge> @endif
            @if ($isHidden) <x-lynx-admin::ui.badge variant="outline">{{ __('lynx-admin::children.hidden') }}</x-lynx-admin::ui.badge> @endif
            @if ($child->hasStatus(\LynxCms\Core\Pages\PageStatus::LOCKED)) <x-lynx-admin::ui.badge variant="outline">{{ __('lynx-admin::children.locked') }}</x-lynx-admin::ui.badge> @endif
            @if (isset($drafts[$id])) <x-lynx-admin::ui.badge variant="secondary">{{ __('lynx-admin::children.draft') }}</x-lynx-admin::ui.badge> @endif
        </span>
        <span class="text-muted-foreground flex flex-wrap gap-x-3 text-xs">
            <span>{{ $child->template()->labels()->in() }}</span>
            @if ($child->updatedAt !== null) <span>{{ __('lynx-admin::children.modified') }} {{ now()->parse($child->updatedAt)->diffForHumans() }}</span> @endif
            @if ($grandchildren > 0)
                <a href="{{ route('lynx.admin.pages.edit', ['page' => $id, 'tab' => 'children']) }}" wire:navigate class="hover:underline" aria-label="{{ __('lynx-admin::children.open_children', ['title' => $title]) }}">{{ trans_choice('lynx-admin::children.children', $grandchildren) }}</a>
            @endif
        </span>
    </div>
    <span class="flex items-center gap-1">
        @if (! $isUnpublished && $child->template()->hasUrl())
            <x-lynx-admin::ui.button variant="ghost" size="icon-sm" :href="$child->url()" target="_blank" rel="noopener" aria-label="{{ __('lynx-admin::children.view', ['title' => $title]) }}" title="{{ __('lynx-admin::children.view', ['title' => $title]) }}"><x-lucide-external-link aria-hidden="true" /></x-lynx-admin::ui.button>
        @endif
        @unless ($isSystem)
        <x-lynx-admin::ui.button variant="ghost" size="icon-sm" type="button" wire:click="toggle({{ $id }}, 'unpublished')"
            aria-label="{{ __($isUnpublished ? 'lynx-admin::children.publish_named' : 'lynx-admin::children.unpublish_named', ['title' => $title]) }}"
            title="{{ __($isUnpublished ? 'lynx-admin::children.publish' : 'lynx-admin::children.unpublish') }}">
            @if ($isUnpublished) <x-lucide-circle-check aria-hidden="true" /> @else <x-lucide-circle-slash aria-hidden="true" /> @endif
        </x-lynx-admin::ui.button>
        @endunless
        <x-lynx-admin::ui.button variant="ghost" size="icon-sm" type="button" wire:click="toggle({{ $id }}, 'hidden')"
            aria-label="{{ __($isHidden ? 'lynx-admin::children.show_named' : 'lynx-admin::children.hide_named', ['title' => $title]) }}"
            title="{{ __($isHidden ? 'lynx-admin::children.show' : 'lynx-admin::children.hide') }}">
            @if ($isHidden) <x-lucide-eye aria-hidden="true" /> @else <x-lucide-eye-off aria-hidden="true" /> @endif
        </x-lynx-admin::ui.button>
        @unless ($isSystem)
        <x-lynx-admin::ui.button variant="ghost" size="icon-sm" type="button" class="hover:text-destructive" wire:click="confirmTrash([{{ $id }}])"
            aria-label="{{ __('lynx-admin::children.trash_named', ['title' => $title]) }}" title="{{ __('lynx-admin::children.trash') }}"><x-lucide-trash-2 aria-hidden="true" /></x-lynx-admin::ui.button>
        @endunless
    </span>
</li>
