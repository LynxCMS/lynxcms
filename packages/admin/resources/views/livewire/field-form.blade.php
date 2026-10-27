{{--
    Setup > Fields > New field and Edit field: a heading with Save, then the field's sections as
    tabs (FieldForm::sections()): a repeater's or matrix's builder, Preview, Field, and Limits and
    display. Tabs switch in the browser, so every input stays in the one form Save submits; a dot
    marks a tab with an error.
--}}
<div class="flex flex-col gap-6" x-data="{ section: @js($sections[0]) }">
    @include('lynx-admin::livewire.partials.field.header')

    @unless ($canEdit)
        <x-lynx-admin::schema-readonly :owner="$owner" />
    @endunless

    @php
        $tabLabels = ['builder' => $type === 'matrix' ? 'Block types' : 'Fields of each item', 'preview' => 'Preview', 'field' => 'Field', 'limits' => 'Limits and display'];
        $errorTabs = collect($errors->keys())->map(fn ($key) => match (true) {
            str_starts_with($key, 'settings.types') || str_starts_with($key, 'settings.fields') => 'builder',
            in_array(explode('.', $key)[1] ?? '', \LynxCms\Admin\Livewire\FieldForm::ITEM_DISPLAY_SETTINGS, true) && $hasItemBuilder => 'limits',
            default => 'field',
        })->unique()->all();
    @endphp
    <nav class="flex gap-5 border-b" aria-label="Field sections">
        @foreach ($sections as $section)
            <button type="button" data-field-tab="{{ $section }}" x-on:click="section = '{{ $section }}'"
                    class="text-muted-foreground relative -mb-px inline-flex items-center gap-1.5 border-b-2 border-transparent pb-2.5 text-sm font-medium hover:text-foreground"
                    x-bind:class="section === '{{ $section }}' && 'border-primary! text-foreground'" x-bind:aria-current="section === '{{ $section }}' ? 'page' : 'false'">
                {{ $tabLabels[$section] }}
                @if ($section === 'builder')<span class="bg-muted rounded-full px-1.5 text-xs tabular-nums">{{ $type === 'matrix' ? count($itemTypes) : count($itemFields) }}</span>@endif
                @if (in_array($section, $errorTabs, true))<span class="bg-destructive size-1.5 rounded-full" aria-label="has errors"></span>@endif
            </button>
        @endforeach
    </nav>

    <form id="field-form" wire:submit="save" class="contents">
        <fieldset class="contents" @disabled(! $canEdit)>
            @if ($hasItemBuilder)
                <div x-show="section === 'builder'" @if ($sections[0] !== 'builder') x-cloak @endif>@include('lynx-admin::livewire.partials.items.builder')</div>
                <div x-show="section === 'limits'" x-cloak>@include('lynx-admin::livewire.partials.items.limits')</div>
            @endif
            <div x-show="section === 'field'" @if ($sections[0] !== 'field') x-cloak @endif>@include('lynx-admin::livewire.partials.field.fields')</div>
            <div x-show="section === 'preview'" x-cloak>@include('lynx-admin::livewire.partials.field.preview')</div>
        </fieldset>
    </form>

    @if ($hasItemBuilder && $canEdit)
        @include('lynx-admin::livewire.partials.items.create-dialog')
    @endif
</div>
