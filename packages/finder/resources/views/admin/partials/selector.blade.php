{{-- The selector the search makes, read only, with a copy button; or why the search cannot run. --}}
<section class="grid grid-cols-1 gap-2" aria-labelledby="finder-selector-heading">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="finder-selector-heading" class="text-sm font-semibold">{{ __('lynx-finder::admin.selector.heading') }}</h2>
        <p class="text-muted-foreground text-sm">{{ __('lynx-finder::admin.selector.help') }}</p>
    </div>
    <div class="bg-muted flex items-center gap-2 rounded-md ps-3" x-data="{ copied: false }">
        <code class="min-w-0 flex-1 overflow-x-auto py-2.5 font-mono text-xs whitespace-nowrap" data-finder-selector>{{ $selector }}</code>
        <x-lynx-admin::ui.button variant="ghost" size="sm" type="button" class="shrink-0"
            x-on:click="navigator.clipboard?.writeText({{ Js::from($selector) }}).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
            <x-lucide-copy aria-hidden="true" x-show="! copied" /><x-lucide-check aria-hidden="true" x-show="copied" x-cloak />
            <span x-text="copied ? {{ Js::from(__('lynx-finder::admin.selector.copied')) }} : {{ Js::from(__('lynx-finder::admin.selector.copy')) }}">{{ __('lynx-finder::admin.selector.copy') }}</span>
        </x-lynx-admin::ui.button>
    </div>
    @if ($error !== null)
        <p role="alert" class="text-destructive text-sm" data-finder-error>{{ __('lynx-finder::admin.selector.error', ['message' => $error]) }}</p>
    @endif
</section>
