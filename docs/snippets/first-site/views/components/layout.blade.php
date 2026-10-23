@props(['page'])
<!DOCTYPE html>
<html lang="{{ lynx()->languages->current()->appLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->title }}</title>
    @hreflang($page)
</head>
<body>
    <header>
        <a href="{{ lynx()->pages->get('/')->url }}">Home</a>
        <a href="{{ lynx()->pages->get('/blog')->url }}">Blog</a>
        @if (lynx()->languages->isMultilingual())
            <nav aria-label="Languages">
                @foreach (lynx()->languages->switcher($page) as $link)
                    <a href="{{ $link['url'] }}" @if ($link['isCurrent']) aria-current="page" @endif>{{ $link['language']->name }}</a>
                @endforeach
            </nav>
        @endif
    </header>
    <main>{{ $slot }}</main>
</body>
</html>
