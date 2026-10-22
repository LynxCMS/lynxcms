<x-lynx-admin::layout :title="$template === null ? 'New template' : 'Template '.$template" :crumbs="['Templates' => route('lynx.admin.templates')]">
    <x-lynx-admin::ui.page-header :title="$template === null ? 'New template' : $template" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-admin.template-form :template="$template" />
</x-lynx-admin::layout>
