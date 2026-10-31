{{-- The field form's heading: the field's label, then its name, kind and templates; Save on the right. --}}
<header class="flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <h1 class="text-2xl font-semibold tracking-tight">{{ $title }}</h1>
        <p class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
            @if ($field === null)
                {{ __('lynx-admin::fields.header.intro') }}
            @else
                <span class="font-mono">{{ $field }}</span>
                <span data-field-kind="{{ $type }}">{{ $kind['label'] }}</span>
                <span>{{ $usedBy === [] ? __('lynx-admin::fields.header.unused') : __('lynx-admin::fields.header.used_by', ['templates' => implode(', ', $usedBy)]) }}</span>
            @endif
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <x-lynx-admin::ui.button variant="ghost" :href="route('lynx.admin.fields')">{{ __($field === null ? 'lynx-admin::fields.header.cancel' : 'lynx-admin::fields.header.all') }}</x-lynx-admin::ui.button>
        @if ($canEdit)
            <x-lynx-admin::ui.button type="submit" form="field-form">{{ __($field === null ? 'lynx-admin::fields.header.create' : 'lynx-admin::fields.header.save') }}</x-lynx-admin::ui.button>
        @endif
    </div>
</header>
