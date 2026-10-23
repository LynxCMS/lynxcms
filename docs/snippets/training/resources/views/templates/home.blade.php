{{-- The home page: Bloom's landing sections, each fed by a field of the home template. --}}
<x-layouts.site :page="$page">
    @include('partials.home.hero')

    @if ($page->stats->count())
        <section class="px-4 py-10" aria-label="@string('home.stats', ['en' => 'Atlas in numbers', 'pt' => 'A Atlas em números'])">
            <div class="mx-auto grid max-w-4xl grid-cols-2 gap-4 sm:grid-cols-3">
                @foreach ($page->stats as $stat)
                    <div class="atlas-stat">
                        <div class="atlas-figure">{{ $stat->figure }}</div>
                        <div class="atlas-muted text-sm">@field($stat->heading)</div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="px-4 py-16">
        <div class="mx-auto grid max-w-5xl gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($page->highlights as $highlight)
                <div class="atlas-clay flex gap-4 p-6">
                    <span class="atlas-icon-tile" style="background:var(--atlas-mint-soft)">
                        <x-dynamic-component :component="'lucide-'.($highlight->icon ?: 'sparkles')" class="size-5" aria-hidden="true" />
                    </span>
                    <div>
                        <h2 class="text-base">@field($highlight->heading)</h2>
                        <p class="atlas-muted text-sm">@field($highlight->text)</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    @include('partials.home.courses')

    @if ($page->steps->count())
        <section class="atlas-section-lavender px-4 py-16">
            <div class="mx-auto max-w-4xl">
                <h2 class="atlas-section-title mb-10 text-center">@string('home.steps', ['en' => 'How booking works', 'pt' => 'Como funciona a reserva'])</h2>
                <ol class="grid gap-6 sm:grid-cols-3">
                    @foreach ($page->steps as $step)
                        <li class="atlas-clay p-6 text-center">
                            <span class="atlas-step">{{ $loop->iteration }}</span>
                            <h3 class="mt-3 text-base">@field($step->heading)</h3>
                            <p class="atlas-muted text-sm">@field($step->text)</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    @if ($page->testimonials->count())
        <section class="px-4 py-16">
            <div class="mx-auto max-w-5xl">
                <h2 class="atlas-section-title mb-10 text-center">@string('home.testimonials', ['en' => 'What graduates say', 'pt' => 'O que dizem os formandos'])</h2>
                <x-ui.carousel class="w-full">
                    <x-ui.carousel-content>
                        @foreach ($page->testimonials as $testimonial)
                            <x-ui.carousel-item class="md:basis-1/2">
                                <figure class="atlas-clay flex h-full flex-col gap-4 p-7">
                                    <x-ui.rating :value="(int) $testimonial->rating" :readonly="true" size="sm" color="text-amber-400" />
                                    <blockquote class="flex-1">“@field($testimonial->text)”</blockquote>
                                    <figcaption class="font-semibold">{{ $testimonial->person }}</figcaption>
                                </figure>
                            </x-ui.carousel-item>
                        @endforeach
                    </x-ui.carousel-content>
                    <x-ui.carousel-previous />
                    <x-ui.carousel-next />
                </x-ui.carousel>
            </div>
        </section>
    @endif

    @include('partials.home.faqs')
</x-layouts.site>
