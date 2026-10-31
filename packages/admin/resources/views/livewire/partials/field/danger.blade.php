{{-- Deleting a field, on its Field tab: a dry run says what goes first (FieldForm::confirmDelete). --}}
@if ($canEdit && $field !== null)
    <section class="border-destructive/40 grid gap-3 rounded-lg border p-4" aria-labelledby="field-delete" @if ($isConfirmingDelete) role="alert" @endif>
        <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
            <div class="grid flex-1 gap-0.5">
                <h2 id="field-delete" class="text-sm font-semibold">{{ __('lynx-admin::fields.delete.heading') }}</h2>
                <p class="text-muted-foreground text-sm">{!! __('lynx-admin::fields.delete.help', ['code' => '<code>$page->'.e($field).'</code>']) !!}</p>
            </div>
            @unless ($isConfirmingDelete)
                <x-lynx-admin::ui.button variant="outline" type="button" wire:click="confirmDelete" class="text-destructive hover:text-destructive">{{ __('lynx-admin::fields.delete.start') }}</x-lynx-admin::ui.button>
            @endunless
        </div>
        @if ($isConfirmingDelete)
            <ul class="list-disc ps-5 text-sm">
                @foreach ($deleteSummary as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
            <div class="flex flex-wrap gap-3">
                <x-lynx-admin::ui.button variant="destructive" type="button" wire:click="delete">{{ __('lynx-admin::fields.delete.confirm', ['name' => $field]) }}</x-lynx-admin::ui.button>
                <x-lynx-admin::ui.button variant="outline" type="button" wire:click="cancelDelete">{{ __('lynx-admin::fields.delete.cancel') }}</x-lynx-admin::ui.button>
            </div>
        @endif
    </section>
@endif
