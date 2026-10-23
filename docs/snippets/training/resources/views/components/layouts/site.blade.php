@props(['page', 'seo' => []])

{{-- The site's frame, from BlatUI's Bloom template: sticky pill navigation, page, footer. --}}
<!DOCTYPE html>
<html lang="{{ lynx()->languages->current()->appLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Title, description, canonical, robots, share cards, hreflang and JSON-LD (chapter 5) --}}
    @seo($page, $seo)
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fredoka:500,600,700|plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="atlas">
    <a href="#main" class="sr-only focus:not-sr-only">@string('layout.skip', ['en' => 'Skip to content', 'pt' => 'Saltar para o conteúdo'])</a>

    <header class="sticky top-0 z-40 flex justify-center px-4 py-3">
        <div class="atlas-nav flex w-full max-w-5xl items-center gap-3 px-5 py-2.5">
            <a href="{{ lynx()->pages->get('/')?->url }}" class="flex items-center gap-2 font-semibold">
                <span class="atlas-icon-tile" style="background:var(--atlas-lilac)"><x-lucide-flower-2 class="size-5" aria-hidden="true" /></span>
                <span class="atlas-logo">Atlas</span>
            </a>

            <x-lynx-menu name="main" view="menus.nav" class="ml-4 hidden md:flex" />

            <div class="ml-auto flex items-center gap-2">
                @include('partials.language-switcher', ['page' => $page])
                <a href="{{ auth()->check() ? route('account') : route('login') }}" class="hidden rounded-full px-3 py-1.5 text-sm font-medium hover:bg-black/5 md:inline-block">
                    @auth @string('layout.account', ['en' => 'My bookings', 'pt' => 'As minhas reservas']) @else @string('layout.sign-in', ['en' => 'Sign in', 'pt' => 'Entrar']) @endauth
                </a>
                <a href="{{ lynx()->pages->get('/courses/')?->url }}" class="atlas-pill-btn atlas-pill-btn-primary hidden sm:inline-flex" style="padding:.55rem 1.4rem;font-size:.9rem">
                    @string('layout.cta', ['en' => 'Find a course', 'pt' => 'Encontrar um curso'])
                </a>
            </div>
        </div>
    </header>

    <main id="main">
        {{ $slot }}
    </main>

    <footer class="atlas-footer px-4 py-14">
        <div class="mx-auto grid max-w-5xl gap-10 sm:grid-cols-3">
            <div>
                <p class="atlas-logo text-white">Atlas Training</p>
                <p class="mt-2 text-sm opacity-60">@string('layout.tagline', ['en' => 'Certified wellness courses since 2011.', 'pt' => 'Cursos certificados de bem-estar desde 2011.'])</p>
            </div>
            <div class="grid gap-6">
                <x-lynx-menu name="footer" view="menus.footer" />
                <x-lynx-island livewire="lynx-form" :with="['form' => 'newsletter', 'pageId' => $page->id]" />
            </div>
            <address class="text-sm not-italic opacity-80">
                @if ($settings = lynx()->settings())
                    <a href="mailto:{{ $settings->email }}">{{ $settings->email }}</a><br>
                    {{ $settings->phone }}
                @endif
            </address>
        </div>
    </footer>
</body>
</html>
