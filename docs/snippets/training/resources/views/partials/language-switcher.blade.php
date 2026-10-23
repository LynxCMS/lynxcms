{{-- Links to this page in each language it is published in, keeping filters in the query string. --}}
@if (lynx()->languages->isMultilingual())
    <nav aria-label="@string('layout.languages', ['en' => 'Languages', 'pt' => 'Idiomas'], scope: 'site')" class="flex rounded-full bg-black/5 p-0.5 text-xs font-semibold">
        @foreach (lynx()->languages->switcher($page) as $link)
            <a href="{{ $link['url'] }}" hreflang="{{ $link['language']->appLocale() }}" lang="{{ $link['language']->appLocale() }}"
               @if ($link['isCurrent']) aria-current="page" @endif
               @class(['rounded-full px-2.5 py-1 uppercase', 'bg-white shadow-sm' => $link['isCurrent']])>{{ $link['language']->code }}</a>
        @endforeach
    </nav>
@endif
