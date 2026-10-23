<x-layout :page="$page">
    <h1>@field($page->title)</h1>
    <p>@field($page->summary)</p>

    @foreach ($page->children('sort=-published_on, limit=20') as $post)
        <article>
            <h2><a href="{{ $post->url }}">@field($post->title)</a></h2>
            <p>@field($post->summary)</p>
        </article>
    @endforeach
</x-layout>
