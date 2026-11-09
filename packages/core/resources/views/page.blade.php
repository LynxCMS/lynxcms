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
    @if (config('app.debug'))
        {{-- For the developer only: which view to write for this template. --}}
        @php($missingView = 'resources/views/'.str_replace('.', '/', (string) config('lynx.views_path', 'templates')).'/'.$page->template()->name.'.blade.php')
        <aside data-lynx-missing-view style="margin: 1rem 0; padding: .75rem 1rem; border: 1px dashed #c58b00; background: #fff8e1; font: 14px/1.5 system-ui, sans-serif">
            {!! __('lynx::page.missing_view', ['view' => '<code>'.e($missingView).'</code>', 'command' => '<code>php artisan lynx:make:view '.e($page->template()->name).'</code>']) !!}
        </aside>
    @endif
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
