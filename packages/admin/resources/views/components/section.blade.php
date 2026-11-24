{{--
    A collapsible section, as the page editor shows a template's sections: a card whose heading
    opens and closes it, with a chevron. It starts as $collapsed says, then as the person last left
    it in this browser (stored under $remember), and opens when $hasError, so it never hides a
    problem (js/editor-section.js). The $meta slot sits beside the title, such as a count.
--}}
@props(['id', 'title', 'remember', 'collapsed' => false, 'hasError' => false, 'bodyClass' => 'grid gap-4 p-5'])
<section {{ $attributes->class('@container bg-card text-card-foreground min-w-0 rounded-xl border shadow-sm') }} data-collapsed="{{ $collapsed ? 'true' : 'false' }}"
         x-data="lynxEditorSection(@js($remember), @js((bool) $collapsed), @js((bool) $hasError))">
    <button type="button" class="flex w-full items-center gap-2 px-5 py-3 text-start" x-on:click="toggle()" x-bind:aria-expanded="open" aria-controls="{{ $id }}">
        <x-lucide-chevron-right class="text-muted-foreground size-4 shrink-0 transition-transform" x-bind:class="open && 'rotate-90'" aria-hidden="true" />
        <span class="font-semibold">{{ $title }}</span>
        {{ $meta ?? '' }}
    </button>
    <div id="{{ $id }}" class="{{ $bodyClass }} rounded-b-xl border-t" x-show="open" @if ($collapsed && ! $hasError) x-cloak @endif>
        {{ $slot }}
    </div>
</section>
