{{-- What to do with the ticked results (ActsOnResults), and the trash's confirmation. --}}
@if ($selected !== [])
    <div class="bg-muted/50 flex flex-wrap items-center gap-2 rounded-md border px-4 py-3 text-sm" role="toolbar" aria-label="{{ trans_choice('lynx-admin::children.selected', count($selected)) }}" data-finder-bulk>
        <span class="me-2 font-medium">{{ trans_choice('lynx-admin::children.selected', count($selected)) }}</span>
        @foreach (['publish', 'unpublish', 'hide', 'show'] as $action)
            <x-lynx-admin::ui.button size="sm" variant="outline" type="button" wire:click="bulk('{{ $action }}')" data-finder-bulk-action="{{ $action }}">{{ __('lynx-admin::children.'.$action) }}</x-lynx-admin::ui.button>
        @endforeach
        <x-lynx-admin::ui.button size="sm" variant="outline" type="button" class="hover:text-destructive" wire:click="confirmTrash({{ Js::from($selected) }})" data-finder-bulk-action="trash">{{ __('lynx-admin::children.trash') }}</x-lynx-admin::ui.button>
    </div>
@endif

@if ($trashing !== [])
    <div class="border-destructive/40 bg-destructive/5 flex flex-wrap items-center gap-3 rounded-md border px-4 py-3 text-sm" role="alert" data-finder-confirm-trash>
        <span class="me-auto">{{ trans_choice('lynx-admin::children.confirm_trash', count($trashing)) }}</span>
        <x-lynx-admin::ui.button size="sm" variant="destructive" type="button" wire:click="trash">{{ __('lynx-admin::children.confirm') }}</x-lynx-admin::ui.button>
        <x-lynx-admin::ui.button size="sm" variant="outline" type="button" wire:click="cancelTrash">{{ __('lynx-admin::children.cancel') }}</x-lynx-admin::ui.button>
    </div>
@endif
