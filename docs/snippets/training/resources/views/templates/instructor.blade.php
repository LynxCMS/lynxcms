<x-layouts.site :page="$page">
    <article class="px-4 py-12">
        <div class="mx-auto max-w-3xl">
            <h1 class="atlas-section-title">@field($page->title)</h1>
            @if ($page->summary)<p class="atlas-lead mt-3">@field($page->summary)</p>@endif
            <div class="atlas-prose mt-8">@field($page->body)</div>
        </div>
    </article>
</x-layouts.site>
