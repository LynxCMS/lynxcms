{{-- Every menu (MenuList), each row opening its editor, and the new menu form in a dialog. --}}
<div>
    @if ($menus === [])
        <x-lynx-admin::empty-state icon="menu" :title="__('lynx-menus::admin.empty')" :help="__('lynx-menus::admin.empty_help')">
            <x-lynx-admin::ui.button type="button" x-on:click="$dispatch('open-dialog-lynx-new-menu')"><x-lucide-plus aria-hidden="true" /> {{ __('lynx-menus::admin.new') }}</x-lynx-admin::ui.button>
        </x-lynx-admin::empty-state>
    @else
        <ul class="bg-card text-card-foreground divide-y overflow-hidden rounded-xl border" aria-label="{{ __('lynx-menus::admin.title') }}">
            @foreach ($menus as $menu)
                <li wire:key="menu-{{ $menu['id'] }}">
                    <a href="{{ route('lynx.admin.menus.edit', $menu['id']) }}" wire:navigate class="group hover:bg-muted/60 focus-visible:ring-ring/50 flex items-center gap-4 px-4 py-3 outline-none focus-visible:ring-[3px] focus-visible:ring-inset" data-menu-row="{{ $menu['id'] }}">
                        <span class="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-md" aria-hidden="true"><x-lucide-menu class="size-4" /></span>
                        <span class="grid min-w-0 flex-1 gap-0.5">
                            <span class="truncate text-[0.9375rem] font-medium group-hover:underline group-hover:underline-offset-4">{{ $menu['title'] ?: $menu['name'] }}</span>
                            <span class="text-muted-foreground truncate font-mono text-xs">{{ $menu['name'] }}</span>
                        </span>
                        <span class="text-muted-foreground shrink-0 text-sm tabular-nums">{{ trans_choice('lynx-menus::admin.list.items', $menu['items']) }}</span>
                        <x-lucide-chevron-right class="text-muted-foreground size-4 shrink-0 rtl:rotate-180" aria-hidden="true" />
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <x-lynx-admin::ui.dialog id="lynx-new-menu" :open="$errors->isNotEmpty()">
        <x-lynx-admin::ui.dialog-content class="sm:max-w-md">
            <form wire:submit="createMenu" class="grid gap-5" aria-labelledby="new-menu-title">
                <x-lynx-admin::ui.dialog-header>
                    <x-lynx-admin::ui.dialog-title id="new-menu-title">{{ __('lynx-menus::admin.new') }}</x-lynx-admin::ui.dialog-title>
                    <x-lynx-admin::ui.dialog-description>{{ __('lynx-menus::admin.new_help') }}</x-lynx-admin::ui.dialog-description>
                </x-lynx-admin::ui.dialog-header>
                <div class="grid gap-2">
                    <span id="new-menu-title-input-label" class="blat-label">{{ __('lynx-menus::admin.title_label') }}</span>
                    @include('lynx-menus::admin.partials.translated-input', ['model' => 'newMenu.title', 'id' => 'new-menu-title-input', 'label' => __('lynx-menus::admin.title_label'), 'error' => 'title', 'placeholder' => __('lynx-menus::admin.title_placeholder')])
                    @error('title') <p class="text-destructive text-sm">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-2">
                    <label for="new-menu-name" class="blat-label">{{ __('lynx-menus::admin.name') }}</label>
                    <input id="new-menu-name" type="text" class="blat-input font-mono" wire:model="newMenu.name" placeholder="{{ __('lynx-menus::admin.name_placeholder') }}" autocomplete="off" spellcheck="false" @error('name') aria-invalid="true" @enderror>
                    <p class="text-muted-foreground text-sm">{{ __('lynx-menus::admin.name_help') }}</p>
                    @error('name') <p class="text-destructive text-sm">{{ $message }}</p> @enderror
                </div>
                <x-lynx-admin::ui.dialog-footer>
                    <x-lynx-admin::ui.button variant="outline" type="button" x-on:click="$dispatch('close-dialog-lynx-new-menu')">{{ __('lynx-menus::admin.settings.cancel') }}</x-lynx-admin::ui.button>
                    <x-lynx-admin::ui.button type="submit">{{ __('lynx-menus::admin.create') }}</x-lynx-admin::ui.button>
                </x-lynx-admin::ui.dialog-footer>
            </form>
        </x-lynx-admin::ui.dialog-content>
    </x-lynx-admin::ui.dialog>
</div>
