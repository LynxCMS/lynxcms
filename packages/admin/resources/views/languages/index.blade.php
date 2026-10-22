<x-lynx-admin::layout title="Languages">
    <x-lynx-admin::ui.page-header title="Languages" description="Content languages, their URL prefixes and fallbacks." class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold">
        @if (app(\LynxCms\Admin\Editor\SchemaOwners::class)->isEditingEnabled())
            <x-slot:actions>
                <x-lynx-admin::ui.button :href="route('lynx.admin.languages.create')"><x-lucide-plus aria-hidden="true" /> New language</x-lynx-admin::ui.button>
            </x-slot:actions>
        @endif
    </x-lynx-admin::ui.page-header>
    @unless (app(\LynxCms\Admin\Editor\SchemaOwners::class)->isEditingEnabled())
        <div class="mb-4"><x-lynx-admin::schema-readonly /></div>
    @endunless
    <livewire:lynx-admin.language-list />
</x-lynx-admin::layout>
