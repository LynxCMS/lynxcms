{{-- Pages elsewhere that reference what is about to be trashed or deleted (ReferenceWarning). --}}
@props(['warning'])
@if ($warning !== null)
    @php($more = $warning['count'] - count($warning['pages']))
    <span {{ $attributes->class('basis-full text-sm') }} data-referenced-by="{{ $warning['count'] }}">
        {{ trans_choice('lynx-admin::pages.references.linked', $warning['count']) }}
        @foreach ($warning['pages'] as $page)
            <a href="{{ route('lynx.admin.pages.edit', $page['id']) }}" class="text-primary font-medium underline-offset-4 hover:underline" data-referencing-page="{{ $page['id'] }}">{{ $page['title'] }}</a>@if (! $loop->last || $more > 0),@endif
        @endforeach
        @if ($more > 0)
            <span data-referenced-more="{{ $more }}">{{ trans_choice('lynx-admin::pages.references.more', $more) }}</span>
        @endif
    </span>
@endif
