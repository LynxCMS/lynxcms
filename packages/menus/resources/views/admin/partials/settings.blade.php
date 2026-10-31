<form wire:submit="saveSettings" class="bg-card text-card-foreground rounded-xl border shadow-sm space-y-5 p-5" aria-labelledby="settings-title">
    <h2 id="settings-title" class="text-base font-semibold">{{ __('lynx-menus::admin.settings.heading') }}</h2>
    @error('page') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror

    <div>
        <span id="settings-title-label" class="blat-label mb-2">{{ __('lynx-menus::admin.title_label') }}</span>
        @include('lynx-menus::admin.partials.translated-input', ['model' => 'settings.title', 'id' => 'settings-title', 'label' => __('lynx-menus::admin.title_label'), 'error' => 'values.title'])
        <p class="text-muted-foreground text-sm mt-1">{{ __('lynx-menus::admin.settings.title_help') }}</p>
    </div>

    <div>
        <label for="settings-class" class="blat-label mb-2">{{ __('lynx-menus::admin.settings.classes') }}</label>
        <input id="settings-class" type="text" class="blat-input" wire:model="settings.class" placeholder="{{ __('lynx-menus::admin.settings.optional') }}">
    </div>

    <div>
        <span class="blat-label mb-2">{{ __('lynx-menus::admin.settings.use') }}</span>
        <code class="lynx-code block">&lt;x-lynx-menu name="{{ $menuPage->name(lynx()->languages->default()) }}" view="lynx-menus::menus.dropdown" /&gt;</code>
        <p class="text-muted-foreground text-sm mt-1">{{ __('lynx-menus::admin.settings.views') }}</p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <x-lynx-admin::ui.button type="submit">{{ __('lynx-menus::admin.settings.save') }}</x-lynx-admin::ui.button>
        @if ($confirmingDelete)
            <span role="alert" class="text-sm text-destructive">{{ __('lynx-menus::admin.settings.confirm_delete') }}</span>
            <x-lynx-admin::ui.button variant="destructive" type="button" wire:click="deleteMenu">{{ __('lynx-menus::admin.settings.yes_delete') }}</x-lynx-admin::ui.button>
            <button type="button" wire:click="cancelDelete" class="text-primary font-medium underline-offset-4 hover:underline text-sm">{{ __('lynx-menus::admin.settings.cancel') }}</button>
        @else
            <button type="button" wire:click="deleteMenu" class="font-medium underline-offset-4 hover:underline ms-auto text-sm text-destructive">{{ __('lynx-menus::admin.settings.delete') }}</button>
        @endif
    </div>
</form>
