<x-layouts.site :page="$page">
    <section class="px-4 py-12">
        <div class="mx-auto max-w-5xl">
            <h1 class="atlas-section-title">@field($page->title)</h1>
            @if ($page->summary)<p class="atlas-lead mt-2">@field($page->summary)</p>@endif
            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($page->children('template=location, sort=title') as $child)
                    <a href="{{ $child->url }}" class="atlas-clay block p-6">
                        <h2 class="text-lg">@field($child->title)</h2>
                        @if ($child->summary)<p class="atlas-muted mt-1 text-sm">@field($child->summary)</p>@endif
                    </a>
                @endforeach
            </div>
        </div>
    </section>
</x-layouts.site>
