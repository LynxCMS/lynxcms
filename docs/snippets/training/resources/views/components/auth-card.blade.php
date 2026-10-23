@props(['title'])

{{-- The frame of the sign-in screens: the site's layout around one clay card. --}}
<x-layouts.site :page="lynx()->pages->get('/')" :seo="['title' => $title, 'robots' => 'noindex, nofollow']">
    <section class="px-4 py-16">
        <div class="atlas-clay mx-auto grid max-w-md gap-6 p-8">
            <h1 class="atlas-section-title text-center">{{ $title }}</h1>
            @if (session('status'))
                <p class="text-center text-sm" role="status">{{ session('status') }}</p>
            @endif
            {{ $slot }}
        </div>
    </section>
</x-layouts.site>
