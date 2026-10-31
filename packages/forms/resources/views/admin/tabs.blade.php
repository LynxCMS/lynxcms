{{-- The Forms screen's tabs: what visitors sent, and the forms themselves. --}}
<nav class="mb-6 flex gap-1 border-b" aria-label="{{ __('lynx-forms::admin.title') }}">
    @foreach (['lynx.admin.forms' => __('lynx-forms::admin.tabs.inbox'), 'lynx.admin.forms.builder' => __('lynx-forms::admin.tabs.forms')] as $route => $label)
        @php($isCurrent = request()->routeIs($route))
        <a href="{{ route($route) }}" @class(['-mb-px border-b-2 px-3 py-2 text-sm font-medium', 'border-primary text-foreground' => $isCurrent, 'border-transparent text-muted-foreground hover:text-foreground' => ! $isCurrent]) @if ($isCurrent) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
