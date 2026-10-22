{{-- A page (SeoData $seo) as a search result and as a social card. --}}
@php
    $parts = parse_url($seo->canonical) ?: [];
    $crumbs = array_values(array_filter([$parts['host'] ?? '', ...explode('/', trim($parts['path'] ?? '', '/'))]));
@endphp
<div class="grid max-w-3xl gap-6">
    <section aria-labelledby="seo-search-result" class="bg-card rounded-xl border p-5">
        <h2 id="seo-search-result" class="text-muted-foreground mb-3 text-xs font-medium uppercase tracking-wide">Search result</h2>
        <p class="text-muted-foreground truncate text-sm">{{ implode(' › ', $crumbs) }}</p>
        <p class="truncate text-xl text-primary">{{ $seo->title }}</p>
        <p class="line-clamp-2 text-sm">{{ $seo->description !== '' ? $seo->description : 'No description: search engines pick text from the page.' }}</p>
        @if ($seo->robots !== null)
            <p class="text-destructive mt-2 text-sm">This page asks search engines not to index it ({{ $seo->robots }}).
                @if (config('lynx-seo.block_non_production') && ! app()->isProduction())
                    Every page does outside production; on the live site this follows the page's own settings.
                @endif
            </p>
        @endif
    </section>

    <section aria-labelledby="seo-social-card" class="bg-card overflow-hidden rounded-xl border">
        <h2 id="seo-social-card" class="text-muted-foreground px-5 pt-5 pb-3 text-xs font-medium uppercase tracking-wide">Shared link</h2>
        @if ($seo->image !== null)
            <img src="{{ $seo->image['url'] }}" alt="{{ $seo->image['alt'] }}" width="{{ $seo->image['width'] }}" height="{{ $seo->image['height'] }}" class="aspect-[1200/630] w-full object-cover">
        @else
            <div class="bg-muted text-muted-foreground flex h-32 w-full items-center justify-center text-sm">No share image: networks may pick one from the page, or show none.</div>
        @endif
        <div class="border-t px-5 py-3">
            <p class="text-muted-foreground text-xs uppercase">{{ $parts['host'] ?? '' }}</p>
            <p class="truncate font-medium">{{ $seo->title }}</p>
            <p class="text-muted-foreground line-clamp-1 text-sm">{{ $seo->description }}</p>
        </div>
    </section>
</div>
