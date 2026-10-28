{{-- The field form's heading: the field's label, then its name, kind and templates; Save on the right. --}}
<header class="flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <h1 class="text-2xl font-semibold tracking-tight">{{ $title }}</h1>
        <p class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
            @if ($field === null)
                A field holds one kind of content. Templates combine fields, and editors fill them in.
            @else
                <span class="font-mono">{{ $field }}</span>
                <span data-field-kind="{{ $type }}">{{ $kind['label'] }}</span>
                <span>{{ $usedBy === [] ? 'No template uses it yet' : 'Used by '.implode(', ', $usedBy) }}</span>
            @endif
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <x-lynx-admin::ui.button variant="ghost" :href="route('lynx.admin.fields')">{{ $field === null ? 'Cancel' : 'All fields' }}</x-lynx-admin::ui.button>
        @if ($canEdit)
            <x-lynx-admin::ui.button type="submit" form="field-form">{{ $field === null ? 'Create field' : 'Save' }}</x-lynx-admin::ui.button>
        @endif
    </div>
</header>
