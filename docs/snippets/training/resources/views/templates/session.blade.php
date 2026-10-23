{{-- One dated session of a course, and its booking form (chapter 7). --}}
@php($course = $page->parent())
<x-layouts.site :page="$page">
    <section class="px-4 py-12">
        <div class="atlas-clay mx-auto max-w-2xl p-8">
            <p class="atlas-muted text-sm"><a href="{{ $course?->url }}">@field($course?->title)</a></p>
            <h1 class="atlas-section-title mt-2">{{ $page->starts->locale(lynx()->languages->current()->appLocale())->isoFormat('dddd, D MMMM YYYY, HH:mm') }}</h1>
            <dl class="mt-6 grid grid-cols-2 gap-4 text-sm">
                <div><dt class="atlas-muted">@string('session.where', ['en' => 'Where', 'pt' => 'Onde'])</dt><dd><a href="{{ $page->location?->url }}">@field($page->location?->title)</a></dd></div>
                <div><dt class="atlas-muted">@string('session.price', ['en' => 'Price', 'pt' => 'Preço'])</dt><dd>{{ Illuminate\Support\Number::currency((float) ($page->price ?? $course?->price), 'EUR', lynx()->languages->current()->appLocale()) }}</dd></div>
                <div><dt class="atlas-muted">@string('session.status', ['en' => 'Status', 'pt' => 'Estado'])</dt><dd>{{ $page->availability }}</dd></div>
            </dl>

            <div class="mt-8">
                <x-lynx-island view="partials.session.booking">
                    <p class="atlas-muted text-sm">…</p>
                </x-lynx-island>
            </div>
        </div>
    </section>
</x-layouts.site>
