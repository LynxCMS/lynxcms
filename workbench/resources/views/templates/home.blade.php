@extends('layouts.site')

@section('content')
    <h1>@field($page->title)</h1>
    <ul>
        @foreach ($page->children() as $section)
            <li><a href="{{ $section->url }}">{{ $section->title }}</a></li>
        @endforeach
    </ul>
@endsection
