<x-lynx-admin::layout :title="__('lynx-admin::languages.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-admin::languages.title')" :description="__('lynx-admin::languages.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold">
        @if (app(\LynxCms\Admin\Editor\SchemaOwners::class)->isEditingEnabled())
            <x-slot:actions>
                <x-lynx-admin::ui.button :href="route('lynx.admin.languages.create')"><x-lucide-plus aria-hidden="true" /> {{ __('lynx-admin::languages.new') }}</x-lynx-admin::ui.button>
            </x-slot:actions>
        @endif
    </x-lynx-admin::ui.page-header>
    @unless (app(\LynxCms\Admin\Editor\SchemaOwners::class)->isEditingEnabled())
        <div class="mb-4"><x-lynx-admin::schema-readonly /></div>
    @endunless
    <livewire:lynx-admin.language-list />
</x-lynx-admin::layout>
