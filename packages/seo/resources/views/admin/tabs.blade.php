{{-- The SEO screen's tabs. The settings are a page, edited in the page editor. --}}
@php($settings = lynx()->settings(\LynxCms\Seo\SeoSchema::SETTINGS_TEMPLATE))
<nav class="mb-6 flex gap-1 border-b" aria-label="SEO">
    @foreach (['lynx.admin.seo' => 'Templates', 'lynx.admin.seo.schemas' => 'Structured data', 'lynx.admin.seo.audit' => 'Audit'] as $route => $label)
        @php($isCurrent = request()->routeIs($route))
        <a href="{{ route($route) }}" @class(['-mb-px border-b-2 px-3 py-2 text-sm font-medium', 'border-primary text-foreground' => $isCurrent, 'border-transparent text-muted-foreground hover:text-foreground' => ! $isCurrent]) @if ($isCurrent) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
    @if ($settings !== null)
        <a href="{{ route('lynx.admin.pages.edit', $settings->id) }}" class="-mb-px border-b-2 border-transparent px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground">Settings</a>
    @endif
</nav>
