{{-- A course: what it is, its curriculum and instructors, and the sessions people can book. --}}
<x-layouts.site :page="$page">
    <article class="px-4 py-12">
        <div class="mx-auto grid max-w-5xl gap-10 lg:grid-cols-[1fr_20rem]">
            <div>
                @if ($page->level)
                    <x-ui.badge class="rounded-full atlas-badge">{{ $page->level }}</x-ui.badge>
                @endif
                <h1 class="atlas-section-title mt-3">@field($page->title)</h1>
                <p class="atlas-lead mt-3">@field($page->summary)</p>
                <div class="atlas-prose mt-8">@field($page->body)</div>

                @if ($page->curriculum->count())
                    <h2 class="mt-10 text-xl">@string('course.curriculum', ['en' => 'What you will learn', 'pt' => 'O que vai aprender'])</h2>
                    <div class="atlas-clay mt-4 p-6">
                        <x-ui.accordion type="multiple">
                            @foreach ($page->curriculum as $module)
                                <x-ui.accordion-item :value="'module-'.$loop->index">
                                    <x-ui.accordion-trigger>{{ $loop->iteration }}. @field($module->heading)</x-ui.accordion-trigger>
                                    <x-ui.accordion-content>@field($module->text)</x-ui.accordion-content>
                                </x-ui.accordion-item>
                            @endforeach
                        </x-ui.accordion>
                    </div>
                @endif

                @if ($page->instructors->count())
                    <h2 class="mt-10 text-xl">@string('course.instructors', ['en' => 'Your instructors', 'pt' => 'Os seus formadores'])</h2>
                    <ul class="mt-4 flex flex-wrap gap-4">
                        @foreach ($page->instructors as $instructor)
                            <li class="flex items-center gap-3">
                                <x-ui.avatar class="size-12">
                                    @if ($photo = $instructor->photos->first())
                                        <x-ui.avatar-image :src="$photo->size(96, 96)->url()" :alt="(string) $instructor->title" />
                                    @endif
                                    <x-ui.avatar-fallback>{{ mb_substr((string) $instructor->title, 0, 1) }}</x-ui.avatar-fallback>
                                </x-ui.avatar>
                                <a href="{{ $instructor->url }}" class="font-semibold">@field($instructor->title)</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="grid h-fit gap-6">
                @include('partials.course.sessions')

                {{-- The enquiry form; the submission keeps this page, so staff know the course --}}
                <section class="atlas-clay p-6" aria-labelledby="enquiry">
                    <h2 id="enquiry" class="text-lg">@string('course.enquiry', ['en' => 'Questions about this course?', 'pt' => 'Dúvidas sobre este curso?'])</h2>
                    {{-- An island: the page cache keeps the course page, and every visitor gets a
                         fresh form (its token and the honeypot's timing) (chapter 10). --}}
                    <x-lynx-island livewire="lynx-form" :with="['form' => 'enquiry', 'pageId' => $page->id]" />
                </section>
            </div>
        </div>
    </article>
</x-layouts.site>
