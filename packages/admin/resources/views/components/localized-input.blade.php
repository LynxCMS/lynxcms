@props(['id', 'model', 'multiline' => false, 'placeholder' => '', 'rows' => 2])
{{-- Schema text per language (ADR-0043): one input, or compact language tabs when there are several. --}}
@php($languages = \LynxCms\Admin\Editor\LocalizedForm::languages())
@if (count($languages) === 1)
    @if ($multiline)
        <textarea id="{{ $id }}" rows="{{ $rows }}" class="blat-textarea" wire:model="{{ $model }}.{{ $languages[0]['code'] }}" placeholder="{{ $placeholder }}"></textarea>
    @else
        <input id="{{ $id }}" type="text" {{ $attributes->class('blat-input') }} wire:model="{{ $model }}.{{ $languages[0]['code'] }}" placeholder="{{ $placeholder }}">
    @endif
@else
    <x-lynx-admin::ui.tabs :value="$languages[0]['code']" class="gap-1">
        <x-lynx-admin::ui.tabs-list variant="compact" aria-label="{{ __('lynx-admin::editor.field.languages') }}">
            @foreach ($languages as $language)
                <x-lynx-admin::ui.tabs-trigger :value="$language['code']">{{ $language['label'] }}</x-lynx-admin::ui.tabs-trigger>
            @endforeach
        </x-lynx-admin::ui.tabs-list>
        @foreach ($languages as $language)
            <div x-show="tab === '{{ $language['code'] }}'" @if (! $loop->first) x-cloak @endif>
                @if ($multiline)
                    <textarea id="{{ $loop->first ? $id : $id.'-'.$language['code'] }}" rows="{{ $rows }}" class="blat-textarea" wire:model="{{ $model }}.{{ $language['code'] }}" placeholder="{{ $placeholder }}" aria-label="{{ $language['label'] }}"></textarea>
                @else
                    <input id="{{ $loop->first ? $id : $id.'-'.$language['code'] }}" type="text" {{ $attributes->class('blat-input') }} wire:model="{{ $model }}.{{ $language['code'] }}" placeholder="{{ $placeholder }}" aria-label="{{ $language['label'] }}">
                @endif
            </div>
        @endforeach
    </x-lynx-admin::ui.tabs>
@endif
