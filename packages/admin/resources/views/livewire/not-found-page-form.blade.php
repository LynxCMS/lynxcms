<form wire:submit="save" class="bg-card text-card-foreground grid content-start gap-5 rounded-xl border p-5 shadow-sm" aria-labelledby="not-found">
    <div class="grid gap-1">
        <h2 id="not-found" class="text-base font-semibold">{{ __('lynx-admin::general.not_found.heading') }}</h2>
        <p class="text-muted-foreground text-sm">{{ __('lynx-admin::general.not_found.help') }}</p>
    </div>

    <div class="grid max-w-md gap-2">
        <livewire:lynx-admin.page-picker wire:model="page" :value="$page" :multiple="false" :placeholder="__('lynx-admin::general.not_found.search')" key="not-found-picker" />
        @error('page') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        @unless ($isShown)
            <p class="text-sm text-[var(--lynx-notice-warning-foreground)]">{{ __('lynx-admin::general.not_found.not_shown') }}</p>
        @endunless
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <x-lynx-admin::ui.button type="submit" wire:loading.attr="disabled">{{ __('lynx-admin::general.not_found.save') }}</x-lynx-admin::ui.button>
        @if ($editUrl)
            <a href="{{ $editUrl }}" class="text-muted-foreground hover:text-foreground text-sm underline-offset-4 hover:underline">{{ __('lynx-admin::general.not_found.edit') }}</a>
        @endif
    </div>
</form>
