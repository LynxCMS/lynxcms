{{-- The inbox's selection: what is selected, a way to select every match, the bulk actions, and the delete confirmation. --}}
@php($count = $this->selectionCount())
<div class="bg-muted/50 grid gap-3 rounded-lg border p-3 text-sm" role="toolbar" aria-label="{{ __('lynx-forms::admin.inbox.selected_label') }}" data-inbox-selection>
    <div class="flex flex-wrap items-center gap-2">
        <span data-selection-count="{{ $count }}">
            @if ($allMatching)
                {{ trans_choice('lynx-forms::admin.inbox.all_matching_selected', $count, ['count' => \LynxCms\Core\Support\LocalizedNumber::format($count)]) }}
            @else
                {{ trans_choice('lynx-forms::admin.inbox.selected', $count, ['count' => \LynxCms\Core\Support\LocalizedNumber::format($count)]) }}
            @endif
        </span>
        @if (! $allMatching && $isPageSelected && $total > count($selected))
            <x-lynx-admin::ui.button variant="link" size="xs" type="button" wire:click="selectAllMatching" data-select-all-matching>{{ trans_choice('lynx-forms::admin.inbox.select_all_matching', $total, ['count' => \LynxCms\Core\Support\LocalizedNumber::format($total)]) }}</x-lynx-admin::ui.button>
        @endif
        <x-lynx-admin::ui.button variant="link" size="xs" type="button" wire:click="clearSelection">{{ __('lynx-forms::admin.inbox.clear_selection') }}</x-lynx-admin::ui.button>
    </div>
    @if ($confirmingDelete === null)
        <div class="flex flex-wrap items-center gap-2">
            <x-lynx-admin::ui.button variant="outline" size="xs" type="button" wire:click="markSelected('read')">{{ __('lynx-forms::admin.inbox.mark_read') }}</x-lynx-admin::ui.button>
            {{-- Archived and spam submissions are purged later, so they need forms.manage, as delete does. --}}
            @if ($canManage)
                <x-lynx-admin::ui.button variant="outline" size="xs" type="button" wire:click="markSelected('archived')">{{ __('lynx-forms::admin.inbox.archive') }}</x-lynx-admin::ui.button>
                <x-lynx-admin::ui.button variant="outline" size="xs" type="button" wire:click="markSelected('spam')">{{ __('lynx-forms::admin.inbox.mark_spam') }}</x-lynx-admin::ui.button>
                <x-lynx-admin::ui.button variant="outline" size="xs" type="button" wire:click="confirmDelete" class="text-destructive hover:text-destructive" data-inbox-delete>{{ __('lynx-forms::admin.inbox.delete') }}</x-lynx-admin::ui.button>
            @endif
        </div>
    @else
        <div class="flex flex-wrap items-center gap-2" role="alert" data-confirm-delete>
            <span>{{ __('lynx-forms::admin.inbox.confirm_delete', [
                'submissions' => trans_choice('lynx-forms::admin.inbox.submissions_count', $confirmingDelete['submissions'], ['count' => \LynxCms\Core\Support\LocalizedNumber::format($confirmingDelete['submissions'])]),
                'files' => trans_choice('lynx-forms::admin.inbox.files', $confirmingDelete['files']),
            ]) }}</span>
            <x-lynx-admin::ui.button variant="outline" size="xs" type="button" wire:click="$set('confirmingDelete', null)">{{ __('lynx-forms::admin.inbox.cancel') }}</x-lynx-admin::ui.button>
            <x-lynx-admin::ui.button variant="destructive" size="xs" type="button" wire:click="deleteSelected">{{ trans_choice('lynx-forms::admin.inbox.yes_delete', $confirmingDelete['submissions'], ['count' => \LynxCms\Core\Support\LocalizedNumber::format($confirmingDelete['submissions'])]) }}</x-lynx-admin::ui.button>
        </div>
    @endif
</div>
