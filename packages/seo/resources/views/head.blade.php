{{-- The SEO tags for one page (SeoData $seo). Publish the view to change them. --}}
<title>{{ $seo->title }}</title>
@if ($seo->description !== '')
<meta name="description" content="{{ $seo->description }}">
@endif
<link rel="canonical" href="{{ $seo->canonical }}">
@if ($seo->robots !== null)
<meta name="robots" content="{{ $seo->robots }}">
@endif
@foreach ($seo->alternates as $link)
<link rel="alternate" hreflang="{{ $link['hreflang'] }}" href="{{ $link['href'] }}">
@endforeach
<meta property="og:type" content="{{ $seo->type }}">
<meta property="og:title" content="{{ $seo->title }}">
@if ($seo->description !== '')
<meta property="og:description" content="{{ $seo->description }}">
@endif
<meta property="og:url" content="{{ $seo->canonical }}">
@if ($seo->siteName !== '')
<meta property="og:site_name" content="{{ $seo->siteName }}">
@endif
<meta property="og:locale" content="{{ $seo->locale }}">
@foreach ($seo->localeAlternates as $locale)
<meta property="og:locale:alternate" content="{{ $locale }}">
@endforeach
@if ($seo->image !== null)
<meta property="og:image" content="{{ $seo->image['url'] }}">
<meta property="og:image:width" content="{{ $seo->image['width'] }}">
<meta property="og:image:height" content="{{ $seo->image['height'] }}">
@if ($seo->image['alt'] !== '')
<meta property="og:image:alt" content="{{ $seo->image['alt'] }}">
@endif
@endif
<meta name="twitter:card" content="{{ $seo->image !== null ? 'summary_large_image' : 'summary' }}">
@if ($seo->twitter !== null)
<meta name="twitter:site" content="{{ $seo->twitter }}">
@endif
@if ($seo->jsonLd !== [])
<script type="application/ld+json">{!! json_encode($seo->jsonLd, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
