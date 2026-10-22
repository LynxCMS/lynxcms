<x-lynx-admin::layout :title="$language === null ? 'New language' : 'Language '.$language" :crumbs="['Languages' => route('lynx.admin.languages')]">
    <x-lynx-admin::ui.page-header :title="$language === null ? 'New language' : $language" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-admin.language-form :language="$language" />
</x-lynx-admin::layout>
