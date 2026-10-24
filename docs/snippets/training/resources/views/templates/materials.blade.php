{{-- A course's materials: only the people who booked the course get here (CourseMaterials). --}}
<x-layouts.site :page="$page" :seo="['robots' => 'noindex, nofollow']">
    <article class="px-4 py-12">
        <div class="mx-auto max-w-3xl">
            <p class="atlas-muted text-sm"><a href="{{ $page->parent()?->url }}">@field($page->parent()?->title)</a></p>
            <h1 class="atlas-section-title mt-2">@field($page->title)</h1>
            <div class="atlas-prose mt-6">@field($page->body)</div>

            {{-- A private field: these addresses work only for people who may open this page. --}}
            @if ($page->downloads?->count())
                <ul class="mt-8 grid gap-3">
                    @foreach ($page->downloads as $file)
                        <li><a href="{{ $file->url() }}" download class="atlas-session">{{ $file->description() ?: $file->name() }}</a></li>
                    @endforeach
                </ul>
            @endif

            {{-- The course survey (chapter 9): members only, one answer each. The page it is sent
                 from says which course it is about. --}}
            <section class="atlas-clay mt-10 p-6" aria-labelledby="feedback">
                <h2 id="feedback" class="text-xl">@string('materials.feedback', ['en' => 'Tell us how it went', 'pt' => 'Diga-nos como correu'], scope: 'site')</h2>
                <livewire:lynx-form form="course-feedback" :page-id="$page->id" />
            </section>
        </div>
    </article>
</x-layouts.site>
