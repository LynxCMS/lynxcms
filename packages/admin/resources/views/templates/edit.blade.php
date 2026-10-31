<x-lynx-admin::layout :title="$template === null ? __('lynx-admin::templates.new') : __('lynx-admin::templates.heading', ['name' => $template])" :crumbs="[__('lynx-admin::templates.title') => route('lynx.admin.templates')]">
    <x-lynx-admin::ui.page-header :title="$template === null ? __('lynx-admin::templates.new') : $template" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <livewire:lynx-admin.template-form :template="$template" />
</x-lynx-admin::layout>
