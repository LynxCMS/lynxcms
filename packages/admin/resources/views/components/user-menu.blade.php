{{-- The signed-in user's menu in the sidebar footer: light, dark or system appearance (kept in this browser) and account security. --}}
@php($user = data_get(auth()->user(), 'name') ?? data_get(auth()->user(), 'email'))
<x-lynx-admin::ui.dropdown-menu>
    <x-lynx-admin::ui.dropdown-menu-trigger>
        <x-lynx-admin::ui.sidebar-menu-button :is-active="request()->routeIs('lynx.admin.account')" :tooltip="$user">
            <x-lynx-admin::ui.icon name="circle-user" aria-hidden="true" />
            <span>{{ $user }}</span>
            <x-lucide-chevrons-up-down class="ms-auto" aria-hidden="true" />
        </x-lynx-admin::ui.sidebar-menu-button>
    </x-lynx-admin::ui.dropdown-menu-trigger>
    <x-lynx-admin::ui.dropdown-menu-content side="top" class="min-w-56">
        <x-lynx-admin::ui.dropdown-menu-label>Appearance</x-lynx-admin::ui.dropdown-menu-label>
        <x-lynx-admin::ui.dropdown-menu-radio-group x-init="radioValue = lynxColorScheme.current(); $watch('radioValue', () => lynxColorScheme.set(radioValue))">
            <x-lynx-admin::ui.dropdown-menu-radio-item value="light"><x-lucide-sun aria-hidden="true" /> Light</x-lynx-admin::ui.dropdown-menu-radio-item>
            <x-lynx-admin::ui.dropdown-menu-radio-item value="dark"><x-lucide-moon aria-hidden="true" /> Dark</x-lynx-admin::ui.dropdown-menu-radio-item>
            <x-lynx-admin::ui.dropdown-menu-radio-item value="system"><x-lucide-monitor aria-hidden="true" /> System</x-lynx-admin::ui.dropdown-menu-radio-item>
        </x-lynx-admin::ui.dropdown-menu-radio-group>
        @if (Route::has('lynx.admin.account'))
            <x-lynx-admin::ui.dropdown-menu-separator />
            <x-lynx-admin::ui.dropdown-menu-item :href="route('lynx.admin.account')"><x-lucide-shield aria-hidden="true" /> Security</x-lynx-admin::ui.dropdown-menu-item>
        @endif
    </x-lynx-admin::ui.dropdown-menu-content>
</x-lynx-admin::ui.dropdown-menu>
