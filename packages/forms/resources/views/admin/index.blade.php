<x-lynx-admin::layout title="Forms">
    <x-lynx-admin::ui.page-header title="Forms" description="What visitors sent through the site's forms." class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    @include('lynx-forms::admin.tabs')
    <livewire:lynx-forms.inbox />
</x-lynx-admin::layout>
