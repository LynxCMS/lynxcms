{{-- Site texts: the template texts every page shares, kept on the home page. Modules add components beside the title
     (AdminSlots::SITE_TEXTS_HEADER), each mounted with the home page's id. --}}
@php
    $home = lynx()->pages->get('/');
    $slotComponents = $home === null ? [] : app(\LynxCms\Admin\AdminSlots::class)->for(\LynxCms\Admin\AdminSlots::SITE_TEXTS_HEADER, $home);
@endphp
<x-lynx-admin::layout :title="__('lynx-admin::pages.site_texts.title')">
    <x-lynx-admin::ui.page-header :title="__('lynx-admin::pages.site_texts.title')" :description="__('lynx-admin::pages.site_texts.description')" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold">
        @if ($slotComponents !== [])
            <x-slot:actions>
                @foreach ($slotComponents as $slotComponent)
                    @livewire($slotComponent, ['pageId' => (int) $home->id], key('site-texts-'.$slotComponent))
                @endforeach
            </x-slot:actions>
        @endif
    </x-lynx-admin::ui.page-header>
    <livewire:lynx-admin.page-strings :page-id="(int) $home?->id" scope="site" />
</x-lynx-admin::layout>
