<form wire:submit="save" class="bg-card text-card-foreground grid content-start gap-5 rounded-xl border p-5 shadow-sm" aria-labelledby="not-found">
    <div class="grid gap-1">
        <h2 id="not-found" class="text-base font-semibold">Page not found</h2>
        <p class="text-muted-foreground text-sm">The page shown, with status 404, when an address matches no page. Editors write it like any other page. With none, the site shows its plain 404.</p>
    </div>

    <div class="grid max-w-md gap-2">
        <livewire:lynx-admin.page-picker wire:model="page" :value="$page" :multiple="false" placeholder="Search for a page" key="not-found-picker" />
        @error('page') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        @unless ($isShown)
            <p class="text-sm text-[var(--lynx-notice-warning-foreground)]">That page is unpublished or in the trash, so the plain 404 shows instead.</p>
        @endunless
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <x-lynx-admin::ui.button type="submit" wire:loading.attr="disabled">Save</x-lynx-admin::ui.button>
        @if ($editUrl)
            <a href="{{ $editUrl }}" class="text-muted-foreground hover:text-foreground text-sm underline-offset-4 hover:underline">Edit the page</a>
        @endif
    </div>
</form>
