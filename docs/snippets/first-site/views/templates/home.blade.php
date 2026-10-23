<x-layout :page="$page">
    <h1>@field($page->title)</h1>

    <h2>Latest posts</h2>
    @foreach (lynx()->pages->find('template=post, sort=-published_on, limit=3') as $post)
        <p><a href="{{ $post->url }}">@field($post->title)</a></p>
    @endforeach
</x-layout>
