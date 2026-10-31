<x-lynx-admin::layout :title="__('lynx-admin::templates.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-admin::templates.title')" :description="__('lynx-admin::templates.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold">
        @if (app(\LynxCms\Admin\Editor\SchemaOwners::class)->isEditingEnabled())
            <x-slot:actions>
                <x-lynx-admin::ui.button :href="route('lynx.admin.templates.create')"><x-lucide-plus aria-hidden="true" /> {{ __('lynx-admin::templates.new') }}</x-lynx-admin::ui.button>
            </x-slot:actions>
        @endif
    </x-lynx-admin::ui.page-header>
    @unless (app(\LynxCms\Admin\Editor\SchemaOwners::class)->isEditingEnabled())
        <div class="mb-4"><x-lynx-admin::schema-readonly /></div>
    @endunless
    <livewire:lynx-admin.template-list />
</x-lynx-admin::layout>
