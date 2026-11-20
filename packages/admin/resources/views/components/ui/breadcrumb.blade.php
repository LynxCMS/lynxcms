{{-- A breadcrumb trail. A screen with two trails names the second one (aria-label), so the two navigations differ. --}}
<nav aria-label="{{ $attributes->get('aria-label', __('lynx-admin::shell.ui.breadcrumb')) }}" data-slot="breadcrumb" {{ $attributes->except('aria-label') }}>
    {{ $slot }}
</nav>
