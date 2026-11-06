{{-- A language's settings: the heading and the breadcrumb name it, or give its code when it has no name. --}}
@php($name = $language === null ? __('lynx-admin::languages.new') : (lynx()->languages->find($language)?->name ?: $language))
<x-lynx-admin::layout :title="$name" :crumbs="[__('lynx-admin::languages.title') => route('lynx.admin.languages')]">
    <x-lynx-admin::ui.page-header :title="$name" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-admin.language-form :language="$language" />
</x-lynx-admin::layout>
