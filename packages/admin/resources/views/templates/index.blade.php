<x-lynx-admin::layout title="Templates">
    <x-lynx-admin::ui.page-header title="Templates" description="Templates decide which fields a page has and where it may live." class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold">
        @if (app(\LynxCms\Admin\Editor\SchemaOwners::class)->isEditingEnabled())
            <x-slot:actions>
                <x-lynx-admin::ui.button :href="route('lynx.admin.templates.create')"><x-lucide-plus aria-hidden="true" /> New template</x-lynx-admin::ui.button>
            </x-slot:actions>
        @endif
    </x-lynx-admin::ui.page-header>
    @unless (app(\LynxCms\Admin\Editor\SchemaOwners::class)->isEditingEnabled())
        <div class="mb-4"><x-lynx-admin::schema-readonly /></div>
    @endunless
    <livewire:lynx-admin.template-list />
</x-lynx-admin::layout>
