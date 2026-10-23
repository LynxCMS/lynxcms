<x-layouts.site :page="$page">
    <section class="px-4 py-12">
        <div class="atlas-clay mx-auto max-w-2xl p-8">
            <h1 class="atlas-section-title">@field($page->title)</h1>
            <address class="mt-4 not-italic">{{ $page->street }}<br>{{ $page->postal_code }} {{ $page->city }}</address>
        </div>
    </section>
</x-layouts.site>
