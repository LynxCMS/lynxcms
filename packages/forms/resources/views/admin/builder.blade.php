<x-lynx-admin::layout title="Forms">
    <x-lynx-admin::ui.page-header title="Forms" description="Forms built here, and forms defined in code." class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    @include('lynx-forms::admin.tabs')
    <livewire:lynx-forms.list />
</x-lynx-admin::layout>
