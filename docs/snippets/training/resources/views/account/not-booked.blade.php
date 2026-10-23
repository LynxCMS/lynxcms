<x-layouts.site :page="$page" :seo="['robots' => 'noindex, nofollow']">
    <section class="px-4 py-16">
        <div class="atlas-clay mx-auto max-w-xl p-8 text-center">
            <h1 class="atlas-section-title">@string('materials.locked', ['en' => 'For people on this course', 'pt' => 'Para quem está neste curso'], scope: 'site')</h1>
            <p class="atlas-lead mt-3">@string('materials.locked-text', ['en' => 'These materials open once you have booked a session of the course.', 'pt' => 'Estes materiais abrem quando reservar uma sessão do curso.'], scope: 'site')</p>
            <a href="{{ $page->parent()?->url }}" class="atlas-pill-btn atlas-pill-btn-primary mt-6">@field($page->parent()?->title)</a>
        </div>
    </section>
</x-layouts.site>
