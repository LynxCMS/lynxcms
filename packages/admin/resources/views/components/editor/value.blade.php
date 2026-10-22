@props(['type', 'value'])
{{-- Read-only display of a stored value. Everything is escaped, including HTML fields. Module types may register their own display view (FieldInputs). --}}
@php($display = app(\LynxCms\Admin\Editor\FieldInputs::class)->display($type))
@if ($display)
    @include($display, ['value' => $value])
@else
@switch($type)
    @case('page')
        {{-- Prepared by FieldViews as [id, title] pairs; a null title means the page no longer exists. --}}
        <ul class="flex flex-wrap gap-x-4 gap-y-1">
            @foreach ((array) $value as $reference)
                <li>
                    @if ($reference['title'] !== null)
                        <a href="{{ route('lynx.admin.pages.edit', $reference['id']) }}" class="text-primary font-medium underline-offset-4 hover:underline">{{ $reference['title'] }}</a>
                    @else
                        <span class="text-muted-foreground text-sm">Missing page #{{ $reference['id'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
        @break
    @case('toggle')
        <p>{{ $value ? 'Yes' : 'No' }}</p>
        @break
    @case('component')
        <pre class="lynx-code">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        @break
    @case('textarea')
        <p class="whitespace-pre-wrap">{{ is_scalar($value) ? $value : json_encode($value) }}</p>
        @break
    @default
        <p class="break-words">{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</p>
@endswitch
@endif
