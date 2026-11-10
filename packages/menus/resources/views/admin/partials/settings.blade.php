{{-- A menu's Settings tab: title and classes, how a template shows it, its stored copies, and deleting it. --}}
@php($card = 'bg-card text-card-foreground grid gap-4 rounded-xl border p-5')
<div class="grid max-w-3xl gap-6" data-menu-settings>
    <form wire:submit="saveSettings" class="{{ $card }}" aria-labelledby="settings-title">
        <h2 id="settings-title" class="text-base font-semibold">{{ __('lynx-menus::admin.settings.heading') }}</h2>
        @error('page') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror

        <div class="grid gap-2">
            <span id="settings-title-input-label" class="blat-label">{{ __('lynx-menus::admin.title_label') }}</span>
            @include('lynx-menus::admin.partials.translated-input', ['model' => 'settings.title', 'id' => 'settings-title-input', 'label' => __('lynx-menus::admin.title_label'), 'error' => 'values.title'])
            <p class="text-muted-foreground text-sm">{{ __('lynx-menus::admin.settings.title_help') }}</p>
        </div>

        <div class="grid gap-2">
            <label for="settings-class" class="blat-label">{{ __('lynx-menus::admin.settings.classes') }}</label>
            <input id="settings-class" type="text" class="blat-input font-mono" wire:model="settings.class" placeholder="{{ __('lynx-menus::admin.settings.optional') }}" spellcheck="false">
            <p class="text-muted-foreground text-sm">{{ __('lynx-menus::admin.settings.classes_help') }}</p>
        </div>

        <div>
            <x-lynx-admin::ui.button type="submit">{{ __('lynx-menus::admin.settings.save') }}</x-lynx-admin::ui.button>
        </div>
    </form>

    <section class="{{ $card }}" aria-labelledby="settings-use">
        <div class="grid gap-1">
            <h2 id="settings-use" class="text-base font-semibold">{{ __('lynx-menus::admin.settings.use') }}</h2>
            <p class="text-muted-foreground text-sm">{{ __('lynx-menus::admin.settings.views') }}</p>
        </div>
        <div class="bg-muted flex items-center gap-2 rounded-md ps-3" x-data="{ copied: false }">
            <code class="min-w-0 flex-1 overflow-x-auto py-2.5 font-mono text-xs whitespace-nowrap" data-menu-tag>{{ $menuTag }}</code>
            <x-lynx-admin::ui.button variant="ghost" size="sm" type="button" class="shrink-0"
                x-on:click="navigator.clipboard?.writeText({{ Js::from($menuTag) }}).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                <x-lucide-copy aria-hidden="true" x-show="! copied" /><x-lucide-check aria-hidden="true" x-show="copied" x-cloak />
                <span x-text="copied ? {{ Js::from(__('lynx-menus::admin.settings.copied')) }} : {{ Js::from(__('lynx-menus::admin.settings.copy')) }}">{{ __('lynx-menus::admin.settings.copy') }}</span>
            </x-lynx-admin::ui.button>
        </div>
    </section>

    <section class="{{ $card }} sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center" aria-labelledby="settings-stored">
        <div class="grid gap-1">
            <h2 id="settings-stored" class="text-base font-semibold">{{ __('lynx-menus::admin.settings.stored') }}</h2>
            <p class="text-muted-foreground text-sm">{{ __('lynx-menus::admin.rebuild_hint') }}</p>
        </div>
        <x-lynx-admin::ui.button variant="outline" type="button" wire:click="rebuild" class="justify-self-start"><x-lucide-refresh-cw aria-hidden="true" /> {{ __('lynx-menus::admin.rebuild') }}</x-lynx-admin::ui.button>
    </section>

    <section class="{{ $card }} border-destructive/40 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center" aria-labelledby="settings-delete">
        <div class="grid gap-1">
            <h2 id="settings-delete" class="text-base font-semibold">{{ __('lynx-menus::admin.settings.delete') }}</h2>
            <p class="text-muted-foreground text-sm">{{ __('lynx-menus::admin.settings.delete_help') }}</p>
        </div>
        @if ($confirmingDelete)
            <div class="flex flex-wrap items-center gap-2 sm:col-span-2" role="alert">
                <span class="text-destructive me-auto text-sm font-medium">{{ __('lynx-menus::admin.settings.confirm_delete') }}</span>
                <x-lynx-admin::ui.button variant="outline" type="button" wire:click="cancelDelete">{{ __('lynx-menus::admin.settings.cancel') }}</x-lynx-admin::ui.button>
                <x-lynx-admin::ui.button variant="destructive" type="button" wire:click="deleteMenu">{{ __('lynx-menus::admin.settings.yes_delete') }}</x-lynx-admin::ui.button>
            </div>
        @else
            <x-lynx-admin::ui.button variant="outline" type="button" wire:click="deleteMenu" class="text-destructive hover:text-destructive justify-self-start"><x-lucide-trash-2 aria-hidden="true" /> {{ __('lynx-menus::admin.settings.delete') }}</x-lynx-admin::ui.button>
        @endif
    </section>
</div>
