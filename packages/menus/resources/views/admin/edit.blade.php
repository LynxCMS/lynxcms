{{-- One menu's screen: its items and settings (MenuEditor). $menu is the menu page's id from the route. --}}
@php($menuTitle = \LynxCms\Core\Support\Value::string(lynx()->pages->get((int) $menu)?->get('title')))
<x-lynx-admin::layout :title="$menuTitle ?: __('lynx-menus::admin.title')" :crumbs="[__('lynx-menus::admin.title') => route('lynx.admin.menus')]">
    <livewire:lynx-menus.editor :menu-id="(int) $menu" />
</x-lynx-admin::layout>
