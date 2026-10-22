{{-- Fallback view used when resources/views/templates/{template}.blade.php does not exist. --}}
<!DOCTYPE html>
<html lang="{{ lynx()->languages->current()->appLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->title }}</title>
    @hreflang($page)
</head>
<body>
    <main>
        <h1>@field($page->title)</h1>
        @foreach ($page->template()->fields as $field)
            @continue($field->name === 'title')
            @php($value = $page->get($field->name))
            @if ($field->type === 'component')
                @lynxComponent($page, $field->name)
            @elseif ($value instanceof \LynxCms\Core\Pages\PageArray)
                <ul>@foreach ($value as $item)<li><a href="{{ $item->url }}">{{ $item->title }}</a></li>@endforeach</ul>
            @elseif ($value instanceof \LynxCms\Core\Pages\Page)
                <p><a href="{{ $value->url }}">{{ $value->title }}</a></p>
            @elseif ($value !== null && ! ($value instanceof \LynxCms\Core\Pages\FieldValue && $value->isEmpty()))
                <section data-field="{{ $field->name }}">@field($value)</section>
            @endif
        @endforeach
    </main>
</body>
</html>
