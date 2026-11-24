{{--
    Setup > Fields > New field and Edit field: a heading with Save, then the field's tabs
    (FieldFormTabs): Basics, Settings, Access, Advanced and Preview, plus a repeater's or matrix's
    builder and limits. Tabs switch in the browser, so every input stays in the one form Save
    submits; a dot marks a tab with an error.
--}}
<div class="flex flex-col gap-6" x-data="{ section: @js($sections[0]) }">
    @include('lynx-admin::livewire.partials.field.header')

    @unless ($canEdit)
        <x-lynx-admin::schema-readonly :owner="$owner" />
    @endunless

    @php
        $tabLabels = ['builder' => __($type === 'matrix' ? 'lynx-admin::fields.tabs.blocks' : 'lynx-admin::fields.tabs.item_fields'), 'preview' => __('lynx-admin::fields.tabs.preview'), 'field' => __('lynx-admin::fields.tabs.field'), 'limits' => __('lynx-admin::fields.tabs.limits'), 'settings' => __('lynx-admin::fields.tabs.settings'), 'access' => __('lynx-admin::fields.tabs.access'), 'advanced' => __('lynx-admin::fields.tabs.advanced')];
        $errorTabs = collect($errors->keys())->map(fn ($key) => $tabs->forError($key))->unique()->all();
    @endphp
    <nav class="flex gap-5 overflow-x-auto border-b" aria-label="{{ __('lynx-admin::fields.tabs.label') }}">
        @foreach ($sections as $section)
            <button type="button" data-field-tab="{{ $section }}" x-on:click="section = '{{ $section }}'"
                    class="text-muted-foreground relative -mb-px inline-flex shrink-0 items-center gap-1.5 border-b-2 whitespace-nowrap border-transparent pb-2.5 text-sm font-medium hover:text-foreground"
                    x-bind:class="section === '{{ $section }}' && 'border-primary! text-foreground'" x-bind:aria-current="section === '{{ $section }}' ? 'page' : 'false'">
                {{ $tabLabels[$section] }}
                @if ($section === 'builder')<span class="bg-muted rounded-full px-1.5 text-xs tabular-nums">{{ $type === 'matrix' ? count($itemTypes) : count($itemFields) }}</span>@endif
                @if (in_array($section, $errorTabs, true))<span class="bg-destructive size-1.5 rounded-full" aria-label="{{ __('lynx-admin::fields.tabs.has_errors') }}"></span>@endif
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
            @if (in_array('settings', $sections, true))
                <div x-show="section === 'settings'" x-cloak class="max-w-3xl">@include('lynx-admin::livewire.partials.field.settings')</div>
            @endif
            @if (in_array('access', $sections, true))
                <div x-show="section === 'access'" x-cloak class="max-w-3xl">@include('lynx-admin::livewire.partials.field.access')</div>
            @endif
            <div x-show="section === 'advanced'" x-cloak class="max-w-3xl">@include('lynx-admin::livewire.partials.field.advanced')</div>
            <div x-show="section === 'preview'" x-cloak>@include('lynx-admin::livewire.partials.field.preview')</div>
        </fieldset>
    </form>

    @if ($hasItemBuilder && $canEdit)
        @include('lynx-admin::livewire.partials.items.create-dialog')
    @endif
</div>
