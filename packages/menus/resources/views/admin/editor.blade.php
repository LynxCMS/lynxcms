<div class="grid items-start gap-8 lg:grid-cols-[15rem_minmax(0,1fr)]">
    <aside class="space-y-6" aria-label="Menus">
        <nav>
            <ul class="space-y-1">
                @forelse ($this->menus as $menu)
                    <li>
                        <button
                            type="button"
                            wire:click="selectMenu({{ $menu['id'] }})"
                            @class(['lynx-tree-row block w-full text-start', 'bg-highlight' => $menu['id'] === $menuId])
                            @if ($menu['id'] === $menuId) aria-current="true" @endif
                        >
                            <span class="block font-medium">{{ $menu['title'] }}</span>
                            <span class="block text-sm text-muted-foreground">{{ $menu['name'] }}</span>
                        </button>
                    </li>
                @empty
                    <li class="text-muted-foreground text-sm">No menus yet.</li>
                @endforelse
            </ul>
        </nav>

        <form wire:submit="createMenu" class="bg-card text-card-foreground rounded-xl border shadow-sm space-y-3 p-4" aria-labelledby="new-menu-title">
            <h2 id="new-menu-title" class="text-sm font-semibold">New menu</h2>
            <div>
                <label for="new-menu-name" class="blat-label mb-2">Name</label>
                <input id="new-menu-name" type="text" class="blat-input" wire:model="newMenu.name" placeholder="e.g. top, footer" @error('name') aria-invalid="true" @enderror>
                @error('name') <p class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
            </div>
            <div>
                <span id="new-menu-title-input-label" class="blat-label mb-2">Title</span>
                @include('lynx-menus::admin.partials.translated-input', ['model' => 'newMenu.title', 'id' => 'new-menu-title-input', 'label' => 'Title', 'error' => 'title', 'languages' => $this->languages])
                @error('title') <p class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
            </div>
            <x-lynx-admin::ui.button type="submit" class="w-full justify-center">Create menu</x-lynx-admin::ui.button>
        </form>
    </aside>

    @if ($menuPage)
        <section class="space-y-6" aria-labelledby="menu-heading">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 id="menu-heading" class="text-lg font-semibold">{{ $menuTitle }}</h2>
                <div class="flex flex-wrap gap-2">
                    <x-lynx-admin::ui.button variant="outline" type="button" wire:click="rebuild" title="Menus rebuild on their own when Lynx saves a change; use this after changes made outside Lynx">Rebuild</x-lynx-admin::ui.button>
                    <x-lynx-admin::ui.button type="button" wire:click="newItem({{ $menuId }})">Add item</x-lynx-admin::ui.button>
                </div>
            </div>

            <livewire:lynx-admin.page-tree
                :root="(string) $menuId"
                events="menu-item"
                :presenter="\LynxCms\Menus\Admin\MenuItemPresenter::class"
                :key="'menu-tree-'.$menuId"
            />

            @if ($isEditingItem)
                @include('lynx-menus::admin.partials.item-form', ['languages' => $this->languages])
            @endif

            @include('lynx-menus::admin.partials.settings', ['languages' => $this->languages])
        </section>
    @else
        <p class="text-muted-foreground text-sm">Create a menu to start.</p>
    @endif
</div>
