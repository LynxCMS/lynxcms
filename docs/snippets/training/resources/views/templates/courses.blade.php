{{-- The catalogue. CourseCatalogue (a view composer) gives it $levels, $filters and $courses. --}}
{{-- A filtered list is not a page of its own: search engines index the full catalogue. --}}
<x-layouts.site :page="$page" :seo="isset($filters['level']) || isset($filters['q']) ? ['robots' => 'noindex, follow', 'canonical' => $page->url] : []">
    <section class="px-4 py-12">
        <div class="mx-auto max-w-5xl">
            <h1 class="atlas-section-title">@field($page->title)</h1>
            <p class="atlas-lead mt-2">@field($page->summary)</p>

            @include('partials.course.filters')

            <p class="atlas-muted mt-6 text-sm" aria-live="polite">
                {{ trans_choice(lynx()->languages->current()->code === 'pt' ? ':count curso|:count cursos' : ':count course|:count courses', $courses->total()) }}
            </p>
            <div class="mt-4">
                @include('partials.course-grid', ['courses' => $courses])
            </div>
            <div class="mt-8">{{ $courses->links() }}</div>
        </div>
    </section>
</x-layouts.site>
