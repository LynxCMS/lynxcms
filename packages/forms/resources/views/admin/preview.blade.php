<x-lynx-admin::layout :title="__('lynx-forms::admin.preview.title')">
    {{-- The default form look, coloured with the admin's theme. --}}
    <style>
        {!! $css !!}
        .lynx-form { --lynx-form-accent: var(--primary); --lynx-form-accent-text: var(--primary-foreground); --lynx-form-line: var(--border); --lynx-form-error: var(--destructive); }
    </style>
    <x-lynx-admin::ui.page-header :title="__('lynx-forms::admin.preview.heading')" :description="__('lynx-forms::admin.preview.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold" />
    <div class="bg-card max-w-3xl rounded-xl border p-6">
        <livewire:lynx-forms.preview :form="$form" />
    </div>
</x-lynx-admin::layout>
