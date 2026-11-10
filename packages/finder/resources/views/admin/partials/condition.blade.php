{{-- One condition: what it looks at, how it compares and the value, whose input follows the subject's kind. --}}
@php
    $subject = $catalog->find($condition['subject']);
    $input = \LynxCms\Finder\Admin\FinderScreen::input($subject?->kind, $condition['operator']);
    $path = "search.groups.{$g}.conditions.{$c}";
    $id = "finder-{$g}-{$c}";
@endphp
<li class="grid grid-cols-[minmax(0,1fr)_auto] gap-2 sm:grid-cols-[minmax(0,12rem)_minmax(0,13rem)_minmax(0,1fr)_auto] sm:items-start" wire:key="finder-condition-{{ $g }}-{{ $c }}-{{ $condition['subject'] }}" data-finder-condition="{{ $g }}-{{ $c }}">
    <select id="{{ $id }}-subject" class="blat-select" wire:model.live="{{ $path }}.subject" aria-label="{{ __('lynx-finder::admin.condition.subject') }}" data-finder-subject>
        <option value="">{{ __('lynx-finder::admin.condition.choose') }}</option>
        <optgroup label="{{ __('lynx-finder::admin.condition.page') }}">
            @foreach ($catalog->properties() as $property)
                <option value="{{ $property->key }}">{{ $property->label }}</option>
            @endforeach
        </optgroup>
        @if ($catalog->fields() !== [])
            <optgroup label="{{ __('lynx-finder::admin.condition.fields') }}">
                @foreach ($catalog->fields() as $field)
                    <option value="{{ $field->key }}">{{ $field->label }}</option>
                @endforeach
            </optgroup>
        @endif
    </select>

    @if ($subject)
        <select id="{{ $id }}-operator" class="blat-select order-1 col-span-2 sm:order-none sm:col-span-1" wire:model.live="{{ $path }}.operator" aria-label="{{ __('lynx-finder::admin.condition.operator') }}" data-finder-operator>
            @foreach ($subject->kind->operators() as $operator)
                <option value="{{ $operator->value }}">{{ __('lynx-finder::admin.operators.'.$operator->value) }}</option>
            @endforeach
        </select>

        <div class="order-1 col-span-2 flex min-w-0 flex-wrap items-center gap-2 sm:order-none sm:col-span-1" data-finder-value="{{ $input }}">
            @switch($input)
                @case('choice')
                    <select class="blat-select" wire:model.live="{{ $path }}.value" aria-label="{{ __('lynx-finder::admin.condition.value') }}">
                        <option value="">{{ __('lynx-finder::admin.condition.choose') }}</option>
                        @foreach ($subject->options as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @break
                @case('pages')
                    <div class="w-full">
                        <livewire:lynx-admin.page-picker wire:model.live="{{ $path }}.pages" :multiple="true" :placeholder="__('lynx-finder::admin.condition.pages_placeholder')" :key="$id.'-pages-'.$condition['subject']" />
                    </div>
                    @break
                @case('none')
                    @break
                @default
                    @php($type = $input === 'number' ? 'number' : ($input === 'date' ? 'date' : 'text'))
                    <input type="{{ $type }}" class="blat-input min-w-0 flex-1" wire:model.live.debounce.400ms="{{ $path }}.value" aria-label="{{ __('lynx-finder::admin.condition.value') }}" @if ($type === 'number') step="any" @endif>
                    @if ($condition['operator'] === 'between')
                        <span class="text-muted-foreground text-sm">{{ __('lynx-finder::admin.condition.to') }}</span>
                        <input type="{{ $type }}" class="blat-input min-w-0 flex-1" wire:model.live.debounce.400ms="{{ $path }}.to" aria-label="{{ __('lynx-finder::admin.condition.to') }}" @if ($type === 'number') step="any" @endif>
                    @endif
            @endswitch
        </div>
    @else
        <span class="hidden sm:block"></span><span class="hidden sm:block"></span>
    @endif

    <x-lynx-admin::ui.button variant="ghost" size="icon" type="button" wire:click="removeCondition({{ $g }}, {{ $c }})" :aria-label="__('lynx-finder::admin.group.remove_condition')" :title="__('lynx-finder::admin.group.remove_condition')" data-finder-remove-condition>
        <x-lucide-x aria-hidden="true" />
    </x-lynx-admin::ui.button>
</li>
