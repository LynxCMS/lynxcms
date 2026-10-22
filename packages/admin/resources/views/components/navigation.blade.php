{{-- The sidebar's links (LynxCms\Admin\Navigation): items, then groups that stay closed until
     opened, unless the current screen is in them. Each browser remembers the groups it opened.
     Opening a group on the collapsed icon rail opens the sidebar first. --}}
@php($isCurrent = fn (array $item): bool => request()->routeIs($item['route'], $item['route'].'.*'))
@php($exists = fn (array $item): bool => Route::has($item['route']))
<nav aria-label="Admin" class="contents">
    @foreach (app(\LynxCms\Admin\Navigation::class)->menu() as $entry)
        @if (isset($entry['items']))
            @php($items = array_values(array_filter($entry['items'], $exists)))
            @continue($items === [])
            @php($isOpen = array_any($items, $isCurrent))
            <x-lynx-admin::ui.sidebar-menu-item
                x-data="{
                    expanded: {{ $isOpen ? 'true' : 'false' }},
                    storageKey: 'lynx.nav.{{ $entry['key'] }}',
                    init() { if (! this.expanded) { try { this.expanded = localStorage.getItem(this.storageKey) === 'open' } catch (e) {} } },
                    flip() {
                        if ($data.collapsed && ! $data.isMobile) { $data.toggle(); this.expanded = true } else { this.expanded = ! this.expanded }
                        try { localStorage.setItem(this.storageKey, this.expanded ? 'open' : 'closed') } catch (e) {}
                    },
                }"
            >
                <x-lynx-admin::ui.sidebar-menu-button type="button" :tooltip="$entry['label']" data-nav-group="{{ $entry['key'] }}" data-open="{{ $isOpen ? 'true' : 'false' }}" x-on:click="flip()" x-bind:aria-expanded="expanded">
                    @if ($entry['icon'])
                        <x-lynx-admin::ui.icon :name="$entry['icon']" aria-hidden="true" />
                    @endif
                    <span>{{ $entry['label'] }}</span>
                    <x-lynx-admin::ui.icon name="chevron-right" class="ms-auto transition-transform" x-bind:class="expanded && 'rotate-90'" aria-hidden="true" />
                </x-lynx-admin::ui.sidebar-menu-button>
                <x-lynx-admin::ui.sidebar-menu-sub x-show="expanded" x-cloak>
                    @foreach ($items as $item)
                        <x-lynx-admin::ui.sidebar-menu-sub-item>
                            <x-lynx-admin::ui.sidebar-menu-sub-button :href="route($item['route'])" :is-active="$isCurrent($item)" :aria-current="$isCurrent($item) ? 'page' : null">
                                <span>{{ $item['label'] }}</span>
                            </x-lynx-admin::ui.sidebar-menu-sub-button>
                        </x-lynx-admin::ui.sidebar-menu-sub-item>
                    @endforeach
                </x-lynx-admin::ui.sidebar-menu-sub>
            </x-lynx-admin::ui.sidebar-menu-item>
        @elseif ($exists($entry))
            <x-lynx-admin::ui.sidebar-menu-item>
                <x-lynx-admin::ui.sidebar-menu-button :href="route($entry['route'])" :is-active="$isCurrent($entry)" :tooltip="$entry['label']" :aria-current="$isCurrent($entry) ? 'page' : null">
                    @if ($entry['icon'])
                        <x-lynx-admin::ui.icon :name="$entry['icon']" aria-hidden="true" />
                    @endif
                    <span>{{ $entry['label'] }}</span>
                </x-lynx-admin::ui.sidebar-menu-button>
                @php($badge = $entry['badge'] !== null ? ($entry['badge'])() : null)
                @if ($badge !== null && $badge !== 0 && $badge !== '')
                    <x-lynx-admin::ui.sidebar-menu-badge>{{ $badge }}</x-lynx-admin::ui.sidebar-menu-badge>
                @endif
            </x-lynx-admin::ui.sidebar-menu-item>
        @endif
    @endforeach
</nav>
