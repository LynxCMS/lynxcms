<x-lynx-admin::layout :title="__('lynx-forms::admin.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-forms::admin.title')" :description="__('lynx-forms::admin.builder_description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    @include('lynx-forms::admin.tabs')
    <livewire:lynx-forms.list />
</x-lynx-admin::layout>
