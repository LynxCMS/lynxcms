@php($current = lynx()->languages->current())
<!DOCTYPE html>
<html lang="{{ $current->appLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->title }}</title>
    @hreflang($page)
    <style>
        body { font: 16px/1.5 system-ui, sans-serif; max-width: 46rem; margin: 2rem auto; padding: 0 1rem; color: #222; }
        nav.languages a { margin-right: .75rem; } nav.languages a[aria-current] { font-weight: 600; }
        .fallback-note { font-size: .8rem; color: #888; }
        footer { margin-top: 3rem; font-size: .85rem; color: #666; }
        .site-header { margin: 1rem 0 2rem; padding-bottom: .5rem; border-bottom: 1px solid #eee; }
        .site-footer-menu { max-width: 20rem; margin-bottom: 1rem; }
        .icon-external::before { content: "↗"; margin-inline-start: .3em; font-size: .85em; }
    </style>
    @livewireStyles
</head>
<body>
    <nav class="languages">
        {{-- Keeps the query string (filters, a draft preview) when switching language. --}}
        @foreach (lynx()->languages->switcher($page) as $link)
            <a href="{{ $link['url'] }}" @if ($link['isCurrent']) aria-current="page" @endif>{{ $link['language']->name }}</a>
        @endforeach
    </nav>
    <header class="site-header">
        <x-lynx-menu name="top" view="lynx-menus::menus.dropdown" />
    </header>

    {{ $slot ?? '' }}
    @yield('content')

    <footer>
        <x-lynx-menu name="footer" view="lynx-menus::menus.accordion" class="site-footer-menu" />
        <p>@field(lynx()->settings()?->footer_text)</p>
    </footer>
    @livewireScripts
</body>
</html>
