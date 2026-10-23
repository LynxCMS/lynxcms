<ul>
    @foreach ($posts as $post)
        <li><a href="{{ $post->url }}">@field($post->title)</a></li>
    @endforeach
</ul>

<p>Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}, {{ $posts->total() }} posts.</p>

{{ $posts->links() }}
