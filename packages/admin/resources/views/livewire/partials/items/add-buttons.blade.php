{{-- The matrix's add buttons as the page editor shows them, one per block type, in this order. --}}
<figure class="bg-muted/40 grid gap-2 rounded-lg border p-3" aria-label="{{ __('lynx-admin::fields.mock.buttons') }}">
    <figcaption class="text-muted-foreground text-xs font-semibold uppercase tracking-wide">{{ __('lynx-admin::fields.mock.below') }}</figcaption>
    <div class="flex flex-wrap gap-2" aria-hidden="true">
        @foreach ($itemTypes as $blockType)
            <span class="bg-background inline-flex h-8 items-center gap-1.5 rounded-md border px-3 text-sm font-medium shadow-xs">
                <x-lucide-plus class="size-4" /> {{ collect($blockType['label'])->filter()->first() ?: ($blockType['slug'] !== '' ? $blockType['slug'] : __('lynx-admin::fields.types.new')) }}
            </span>
        @endforeach
    </div>
</figure>
