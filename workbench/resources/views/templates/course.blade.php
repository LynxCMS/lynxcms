@extends('layouts.site')

@section('content')
    <p><a href="{{ $page->parent->url }}">← {{ $page->parent->title }}</a></p>
    <h1>@field($page->title)</h1>
    <p><strong>@field($page->summary)</strong></p>
    @field($page->body)
    <p>{{ $page->price }} USD</p>

    @if ($page->related->count())
        <h2>{{ __('Related') }}</h2>
        <ul>@foreach ($page->related as $item)<li><a href="{{ $item->url }}">{{ $item->title }}</a></li>@endforeach</ul>
    @endif

    @lynxComponent($page, 'enrol')
@endsection
