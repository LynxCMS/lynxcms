{{--
    The Preview tab: the field as editors see it in the page editor, from what the form holds now.
    Plain inputs are the page editor's own (FieldPreviewInput), so a rich text field shows its
    editor and tools; the key changes with the settings, so the rich text editor starts again.
--}}
@php
    $shownLabel = collect($label)->filter()->first() ?: ($name !== '' ? Str::headline($name) : __('lynx-admin::fields.preview.label'));
    $shownHelp = collect($description)->filter()->first();
    $shownNote = collect($notes)->filter()->first();
    $addText = collect($addLabel)->filter()->first() ?: __('lynx-admin::fields.preview.add');
    $choices = app(\LynxCms\Admin\Editor\FieldPreviewChoices::class)->for($type, $settings);
    $previewInput = app(\LynxCms\Admin\Fields\FieldPreviewInput::class);
    $inputView = $previewInput->view($type);
@endphp
<div class="grid max-w-2xl gap-3" data-field-preview>
    <p class="text-muted-foreground text-sm">{{ __('lynx-admin::fields.preview.intro') }}</p>
    <div class="grid gap-2 rounded-lg border border-dashed p-4 text-sm">
        {{-- The inputs name themselves after this label (aria-labelledby="field-{name}"). --}}
        <span class="font-medium" id="field-preview-{{ $name !== '' ? $name : 'field' }}">{{ $shownLabel }}</span>
        @if ($shownHelp)<span class="text-muted-foreground">{{ $shownHelp }}</span>@endif
        @if ($inputView !== null)
            <div wire:key="preview-{{ $type }}-{{ md5((string) json_encode($settings)) }}" data-preview-input="{{ $type }}">
                @include($inputView, [
                    'field' => $previewInput->field($name, $shownLabel, $type, $settings, null),
                    'code' => lynx()->languages->default()->code,
                    'model' => \LynxCms\Admin\Fields\FieldPreviewInput::MODEL,
                    'placeholder' => '',
                    'disabled' => false,
                    'live' => false,
                    'value' => $previewValue,
                    'readOnly' => null,
                    'page' => null,
                ])
            </div>
        @elseif ($type === 'matrix')
            @forelse ($itemTypes as $blockType)
                @include('lynx-admin::livewire.partials.items.block-mock', ['title' => collect($blockType['label'])->filter()->first() ?: ($blockType['slug'] ?: __('lynx-admin::fields.preview.new_block_type')), 'rows' => $blockType['fields']])
            @empty
                <span class="text-muted-foreground">{{ __('lynx-admin::fields.preview.block_types_here') }}</span>
            @endforelse
            @if ($itemTypes !== []) @include('lynx-admin::livewire.partials.items.add-buttons') @endif
        @elseif ($type === 'repeater')
            @include('lynx-admin::livewire.partials.items.block-mock', ['title' => __('lynx-admin::fields.preview.new_item'), 'rows' => $itemFields])
            <span class="bg-background inline-flex h-8 w-fit items-center gap-1.5 rounded-md border px-3 font-medium shadow-xs" aria-hidden="true"><x-lucide-plus class="size-4" /> {{ $addText }}</span>
        @elseif ($type === 'toggle' && ($settings['input'] ?? '') === 'switch')
            <span class="inline-flex" data-preview-input="switch" inert><x-lynx-admin::ui.switch /></span>
        @elseif ($type === 'toggle')
            {{-- As inputs/toggle draws it: one checkbox labeled Yes. --}}
            <label class="inline-flex items-center gap-2" data-preview-input="checkbox" inert>
                <input type="checkbox" class="blat-checkbox"> {{ __('lynx-admin::inputs.yes') }}
            </label>
        @elseif ($choices !== null)
            @include('lynx-admin::livewire.partials.field.preview-choices', $choices)
        @elseif (in_array($type, ['images', 'files'], true))
            <span class="text-muted-foreground grid h-20 place-items-center rounded-md border border-dashed" aria-hidden="true">{{ __($type === 'images' ? 'lynx-admin::fields.preview.drop_images' : 'lynx-admin::fields.preview.drop_files') }}</span>
        @else
            <span @class(['bg-background block rounded-md border', 'h-24' => $type === 'textarea', 'h-9' => $type !== 'textarea']) aria-hidden="true"></span>
        @endif
        @if ($shownNote)<span class="text-muted-foreground text-xs">{{ $shownNote }}</span>@endif
    </div>
</div>
