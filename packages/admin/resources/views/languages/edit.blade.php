<x-lynx-admin::layout :title="$language === null ? __('lynx-admin::languages.new') : __('lynx-admin::languages.heading', ['name' => $language])" :crumbs="[__('lynx-admin::languages.title') => route('lynx.admin.languages')]">
    <x-lynx-admin::ui.page-header :title="$language === null ? __('lynx-admin::languages.new') : $language" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-admin.language-form :language="$language" />
</x-lynx-admin::layout>
