<x-lynx-admin::layout :title="__('lynx-forms::admin.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-forms::admin.title')" :description="__('lynx-forms::admin.personal.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    @include('lynx-forms::admin.tabs')
    <livewire:lynx-forms.personal-data />
</x-lynx-admin::layout>
