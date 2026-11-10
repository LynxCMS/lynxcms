{{--
    One menu (MenuEditor): its title and name with Add item, then two tabs. Items shows the tree
    with the item form in a panel beside it (below it on small screens); Settings holds the title,
    classes, how a template shows the menu, its stored copies and deletion.
--}}
<div class="grid gap-6">
    @if ($menuPage)
        <x-lynx-admin::ui.page-header :title="$menuTitle ?: $menuName" class="[&_h1]:text-2xl [&_h1]:font-semibold">
            <x-slot:description>
                <span class="text-muted-foreground flex items-center gap-2 text-sm">
                    {{ __('lynx-menus::admin.name') }} <code class="bg-muted text-foreground rounded-sm px-1.5 py-0.5 font-mono text-xs" data-menu-name>{{ $menuName }}</code>
                </span>
            </x-slot:description>
            <x-slot:actions>
                <x-lynx-admin::ui.button type="button" wire:click="newItem({{ $menuId }})" data-add-item><x-lucide-plus aria-hidden="true" /> {{ __('lynx-menus::admin.add_item') }}</x-lynx-admin::ui.button>
            </x-slot:actions>
        </x-lynx-admin::ui.page-header>

        <x-lynx-admin::ui.tabs :value="$tab" wire:key="menu-tabs-{{ $tab }}">
            <x-lynx-admin::ui.tabs-list variant="underline" aria-label="{{ __('lynx-menus::admin.sections') }}" data-shortcut-tabs>
                <x-lynx-admin::ui.tabs-trigger value="items" wire:click="$set('tab', 'items')">{{ __('lynx-menus::admin.tab_items') }}</x-lynx-admin::ui.tabs-trigger>
                <x-lynx-admin::ui.tabs-trigger value="settings" wire:click="$set('tab', 'settings')">{{ __('lynx-menus::admin.tab_settings') }}</x-lynx-admin::ui.tabs-trigger>
            </x-lynx-admin::ui.tabs-list>
        </x-lynx-admin::ui.tabs>

        @if ($tab === 'items')
            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,26rem)]" data-menu-items>
                <livewire:lynx-admin.page-tree
                    :root="(string) $menuId"
                    events="menu-item"
                    :presenter="\LynxCms\Menus\Admin\MenuItemPresenter::class"
                    :key="'menu-tree-'.$menuId"
                />

                <aside class="lg:sticky lg:top-20" aria-label="{{ __('lynx-menus::admin.item.panel') }}" data-menu-panel>
                    @if ($isEditingItem)
                        @include('lynx-menus::admin.partials.item-form', ['languages' => $this->languages])
                    @else
                        <x-lynx-admin::empty-state compact icon="mouse-pointer-click" :title="__('lynx-menus::admin.item.none')" :help="__('lynx-menus::admin.item.none_help')" />
                    @endif
                </aside>
            </div>
        @else
            @include('lynx-menus::admin.partials.settings', ['languages' => $this->languages])
        @endif
    @endif
</div>
