@extends('layouts.site')

@section('content')
    <h1>@field($page->title)</h1>
    <p>@field($page->summary)</p>
    <ul>
        @foreach (lynx()->pages->find("parent={$page->id}, template=course, sort=title") as $course)
            <li>
                <a href="{{ $course->url }}">{{ $course->title }}</a>
                @if ($course->title->isFallback()) <span class="fallback-note">({{ $course->title->resolvedLanguage()->name }})</span> @endif
                — {{ $course->price }} USD
            </li>
        @endforeach
    </ul>
@endsection
