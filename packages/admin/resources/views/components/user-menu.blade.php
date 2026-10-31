{{-- The signed-in user's menu in the sidebar footer: light, dark or system appearance and translation hints (kept in this browser), the admin's language, and account security. --}}
@php($user = data_get(auth()->user(), 'name') ?? data_get(auth()->user(), 'email'))
@php($locales = \LynxCms\Admin\Locale\AdminLocale::options())
<x-lynx-admin::ui.dropdown-menu>
    <x-lynx-admin::ui.dropdown-menu-trigger>
        <x-lynx-admin::ui.sidebar-menu-button :is-active="request()->routeIs('lynx.admin.account')" :tooltip="$user">
            <x-lynx-admin::ui.icon name="circle-user" aria-hidden="true" />
            <span>{{ $user }}</span>
            <x-lucide-chevrons-up-down class="ms-auto" aria-hidden="true" />
        </x-lynx-admin::ui.sidebar-menu-button>
    </x-lynx-admin::ui.dropdown-menu-trigger>
    <x-lynx-admin::ui.dropdown-menu-content side="top" class="min-w-56">
        <x-lynx-admin::ui.dropdown-menu-label>{{ __('lynx-admin::account.menu.appearance') }}</x-lynx-admin::ui.dropdown-menu-label>
        <x-lynx-admin::ui.dropdown-menu-radio-group x-init="radioValue = lynxColorScheme.current(); $watch('radioValue', () => lynxColorScheme.set(radioValue))">
            <x-lynx-admin::ui.dropdown-menu-radio-item value="light"><x-lucide-sun aria-hidden="true" /> {{ __('lynx-admin::account.menu.light') }}</x-lynx-admin::ui.dropdown-menu-radio-item>
            <x-lynx-admin::ui.dropdown-menu-radio-item value="dark"><x-lucide-moon aria-hidden="true" /> {{ __('lynx-admin::account.menu.dark') }}</x-lynx-admin::ui.dropdown-menu-radio-item>
            <x-lynx-admin::ui.dropdown-menu-radio-item value="system"><x-lucide-monitor aria-hidden="true" /> {{ __('lynx-admin::account.menu.system') }}</x-lynx-admin::ui.dropdown-menu-radio-item>
        </x-lynx-admin::ui.dropdown-menu-radio-group>
        @if (lynx()->languages->isMultilingual())
            {{-- Sentences explaining fallbacks under fields; the language tabs' markers show it without them. --}}
            <x-lynx-admin::ui.dropdown-menu-separator />
            <x-lynx-admin::ui.dropdown-menu-checkbox-item data-lynx-hints-toggle x-init="checked = lynxTranslationHints.isOn(); $watch('checked', () => lynxTranslationHints.set(checked))">
                {{ __('lynx-admin::account.menu.translation_hints') }}
            </x-lynx-admin::ui.dropdown-menu-checkbox-item>
        @endif
        @if (count($locales) > 1)
            <x-lynx-admin::ui.dropdown-menu-separator />
            <x-lynx-admin::ui.dropdown-menu-label>{{ __('lynx-admin::account.menu.language') }}</x-lynx-admin::ui.dropdown-menu-label>
            <form method="POST" action="{{ route('lynx.admin.locale') }}" data-lynx-locale>
                @csrf
                @foreach ($locales as $code => $name)
                    @php($isCurrent = $code === app()->getLocale())
                    <x-lynx-admin::ui.dropdown-menu-item type="submit" name="locale" value="{{ $code }}" lang="{{ $code }}" :inset="! $isCurrent" :aria-current="$isCurrent ? 'true' : null">
                        @if ($isCurrent)<x-lucide-check aria-hidden="true" />@endif {{ $name }}
                    </x-lynx-admin::ui.dropdown-menu-item>
                @endforeach
            </form>
        @endif
        @if (Route::has('lynx.admin.account'))
            <x-lynx-admin::ui.dropdown-menu-separator />
            <x-lynx-admin::ui.dropdown-menu-item :href="route('lynx.admin.account')"><x-lucide-shield aria-hidden="true" /> {{ __('lynx-admin::account.menu.security') }}</x-lynx-admin::ui.dropdown-menu-item>
        @endif
    </x-lynx-admin::ui.dropdown-menu-content>
</x-lynx-admin::ui.dropdown-menu>
