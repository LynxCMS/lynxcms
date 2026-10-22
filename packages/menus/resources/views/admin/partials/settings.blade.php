<form wire:submit="saveSettings" class="bg-card text-card-foreground rounded-xl border shadow-sm space-y-5 p-5" aria-labelledby="settings-title">
    <h2 id="settings-title" class="text-base font-semibold">Menu settings</h2>
    @error('page') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror

    <div>
        <span id="settings-title-label" class="blat-label mb-2">Title</span>
        @include('lynx-menus::admin.partials.translated-input', ['model' => 'settings.title', 'id' => 'settings-title', 'label' => 'Title', 'error' => 'values.title'])
        <p class="text-muted-foreground text-sm mt-1">Shown to editors and used as the menu's accessible name.</p>
    </div>

    <div>
        <label for="settings-class" class="blat-label mb-2">CSS classes</label>
        <input id="settings-class" type="text" class="blat-input" wire:model="settings.class" placeholder="Optional">
    </div>

    <div>
        <span class="blat-label mb-2">Use in a template</span>
        <code class="lynx-code block">&lt;x-lynx-menu name="{{ $menuPage->name(lynx()->languages->default()) }}" view="lynx-menus::menus.dropdown" /&gt;</code>
        <p class="text-muted-foreground text-sm mt-1">Views: menus.list, menus.dropdown (hover), menus.accordion (click), or any view of your own.</p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <x-lynx-admin::ui.button type="submit">Save settings</x-lynx-admin::ui.button>
        @if ($confirmingDelete)
            <span role="alert" class="text-sm text-destructive">Delete this menu and all its items?</span>
            <x-lynx-admin::ui.button variant="destructive" type="button" wire:click="deleteMenu">Yes, delete</x-lynx-admin::ui.button>
            <button type="button" wire:click="cancelDelete" class="text-primary font-medium underline-offset-4 hover:underline text-sm">Cancel</button>
        @else
            <button type="button" wire:click="deleteMenu" class="font-medium underline-offset-4 hover:underline ms-auto text-sm text-destructive">Delete menu</button>
        @endif
    </div>
</form>
