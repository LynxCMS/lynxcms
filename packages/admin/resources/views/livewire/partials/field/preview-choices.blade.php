{{-- A choice or page field in the Preview tab: the control its "input" setting names (FieldPreviewChoices), with its first options. --}}
<div data-preview-input="{{ $style }}" inert>
    @if ($style === 'search')
        <span class="bg-background text-muted-foreground flex h-9 items-center gap-2 rounded-md border px-3" aria-hidden="true">
            <x-lucide-search class="size-4" /> {{ __('lynx-admin::fields.preview.search_pages') }}
        </span>
    @elseif ($options === [])
        <span class="text-muted-foreground">{{ __($type === 'choice' ? 'lynx-admin::fields.preview.options_here' : 'lynx-admin::fields.preview.pages_here') }}</span>
    @elseif ($style === 'select')
        <select class="blat-select max-w-sm">
            @foreach ($options as $option)
                <option value="{{ $option['value'] }}" data-preview-option="{{ $option['value'] }}">{{ $option['label'] }}</option>
            @endforeach
        </select>
    @else
        <div class="flex flex-wrap gap-x-5 gap-y-2">
            @foreach ($options as $option)
                <label class="flex items-center gap-2" data-preview-option="{{ $option['value'] }}">
                    <input type="{{ $style === 'checkboxes' ? 'checkbox' : 'radio' }}" class="{{ $style === 'checkboxes' ? 'blat-checkbox' : 'blat-radio' }}">
                    {{ $option['label'] }}
                </label>
            @endforeach
        </div>
    @endif
</div>
