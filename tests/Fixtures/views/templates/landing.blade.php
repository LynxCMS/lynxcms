@extends('layouts.strings')
@section('main')
<h1>@string('hero.title', ['default' => 'Audiovisual productions', 'pt' => 'Produções audiovisuais'], label: 'Hero title')</h1>
<div class="intro">@string('hero.intro', 'We tell stories with **images**.', format: 'markdown')</div>
@include('partials.cta')
@endsection
