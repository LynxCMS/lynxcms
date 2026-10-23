@php($levels = lynx()->fields->choices('level'))
<section class="px-4 py-16" id="courses">
    <div class="mx-auto max-w-5xl">
        <x-ui.tabs value="featured">
            <div class="mb-8 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-end">
                <h2 class="atlas-section-title">@string('home.courses', ['en' => 'Find your course', 'pt' => 'Encontre o seu curso'])</h2>
                <x-ui.tabs-list class="!rounded-full">
                    <x-ui.tabs-trigger value="featured">@string('home.tab.featured', ['en' => 'Featured', 'pt' => 'Em destaque'])</x-ui.tabs-trigger>
                    @foreach ($levels as $level)
                        <x-ui.tabs-trigger :value="$level->value">{{ $level }}</x-ui.tabs-trigger>
                    @endforeach
                </x-ui.tabs-list>
            </div>

            <x-ui.tabs-content value="featured">
                @include('partials.course-grid', ['courses' => $page->featured])
            </x-ui.tabs-content>
            @foreach ($levels as $level)
                <x-ui.tabs-content :value="$level->value">
                    @include('partials.course-grid', ['courses' => lynx()->pages->find("template=course, level={$level->value}, sort=title, limit=6")])
                </x-ui.tabs-content>
            @endforeach
        </x-ui.tabs>
    </div>
</section>
