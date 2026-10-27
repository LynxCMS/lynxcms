@props(['title' => 'Lynx'])
{{-- The admin's HTML document: theme, appearance, fonts and stylesheets. Screens put their <body> in the slot. --}}
@php
    $scheme = config('lynx-admin.theme', 'system');
    $settings = (array) config('lynx-admin.appearance', []);
    $appearance = \LynxCms\Admin\Appearance::html($settings);
    $fonts = ($settings['load_fonts'] ?? true) ? \LynxCms\Admin\Appearance::fontsUrl($settings) : null;
    // The site's name and accent (Settings > General): inline on <html>, so they win in light and dark.
    $branding = \LynxCms\Admin\Branding\Branding::current();
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-lynx-theme="{{ $scheme }}"
    @if ($scheme === 'dark') class="dark" @endif
    {!! $appearance !!}
    @if ($branding->accentStyle() !== '') style="{{ $branding->accentStyle() }}" @endif
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ $branding->name }}</title>
    {{-- Lynx's favicon until the site brands the admin (ADR-0089); then browsers fall back to the site's own /favicon.ico. --}}
    @if ($branding->usesLynxMark())
        <link rel="icon" href="{{ route('lynx.admin.assets.favicon', ['extension' => 'ico', 'v' => \LynxCms\Admin\Http\AssetController::versionOf(\LynxCms\Admin\Http\AssetController::FAVICONS['ico'])], false) }}" sizes="32x32">
        <link rel="icon" href="{{ route('lynx.admin.assets.favicon', ['extension' => 'svg', 'v' => \LynxCms\Admin\Http\AssetController::versionOf(\LynxCms\Admin\Http\AssetController::FAVICONS['svg'])], false) }}" type="image/svg+xml">
    @endif
    {{-- Before the stylesheet, so the scheme the user picked (or the configured one) never flashes the other. --}}
    <script>(() => { let scheme = @js($scheme); try { scheme = localStorage.getItem('lynx:color-scheme') || scheme } catch (e) {} document.documentElement.classList.toggle('dark', scheme === 'dark' || (scheme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches)) })()</script>
    @if ($fonts)
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="stylesheet" href="{{ $fonts }}">
    @endif
    <link rel="stylesheet" href="{{ route('lynx.admin.assets.css', ['v' => \LynxCms\Admin\Http\AssetController::version()], false) }}">
    @foreach ((array) config('lynx-admin.stylesheets', []) as $stylesheet)
        <link rel="stylesheet" href="{{ $stylesheet }}">
    @endforeach
    @livewireStyles
</head>
{{ $slot }}
</html>
