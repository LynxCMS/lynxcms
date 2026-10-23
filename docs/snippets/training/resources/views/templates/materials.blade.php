{{-- A course's materials: only the people who booked the course get here (CourseMaterials). --}}
<x-layouts.site :page="$page" :seo="['robots' => 'noindex, nofollow']">
    <article class="px-4 py-12">
        <div class="mx-auto max-w-3xl">
            <p class="atlas-muted text-sm"><a href="{{ $page->parent()?->url }}">@field($page->parent()?->title)</a></p>
            <h1 class="atlas-section-title mt-2">@field($page->title)</h1>
            <div class="atlas-prose mt-6">@field($page->body)</div>
        </div>
    </article>
</x-layouts.site>
