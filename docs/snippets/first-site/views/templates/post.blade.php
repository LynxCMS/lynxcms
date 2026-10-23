<x-layout :page="$page">
    <article>
        <h1>@field($page->title)</h1>
        <p><time datetime="{{ $page->published_on?->toDateString() }}">{{ $page->published_on?->toFormattedDateString() }}</time></p>
        @field($page->body)
    </article>
</x-layout>
