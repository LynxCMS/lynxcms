<div class="space-y-6">
    <x-lynx-admin::ui.breadcrumb>
        <x-lynx-admin::ui.breadcrumb-list>
            <x-lynx-admin::ui.breadcrumb-item><x-lynx-admin::ui.breadcrumb-link :href="route('lynx.admin.forms')">Forms</x-lynx-admin::ui.breadcrumb-link></x-lynx-admin::ui.breadcrumb-item>
        </x-lynx-admin::ui.breadcrumb-list>
    </x-lynx-admin::ui.breadcrumb>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $form?->text('label')->in() ?? $submission->form }}</h1>
            <p class="text-muted-foreground text-sm">
                Received {{ $submission->created_at->toDayDateTimeString() }}
                @if ($submission->language() && lynx()->languages->isMultilingual()) · in {{ $submission->language()->name }} @endif
                @if ($page) · from <a href="{{ $page->url }}" class="underline" target="_blank" rel="noopener">{{ $page->title }}</a> @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-lynx-admin::ui.badge :variant="$submission->status === 'new' ? 'default' : 'secondary'">{{ $submission->status }}</x-lynx-admin::ui.badge>
            @foreach (['read' => 'Mark read', 'archived' => 'Archive', 'spam' => 'Spam', 'new' => 'Mark new'] as $value => $label)
                @if ($submission->status !== $value)
                    <x-lynx-admin::ui.button variant="outline" size="sm" wire:click="setStatus('{{ $value }}')">{{ $label }}</x-lynx-admin::ui.button>
                @endif
            @endforeach
            @if ($canDelete)
                <span x-data="{ confirming: false }" class="flex items-center gap-2">
                    <x-lynx-admin::ui.button variant="outline" size="sm" x-show="! confirming" x-on:click="confirming = true">Delete</x-lynx-admin::ui.button>
                    <span x-show="confirming" x-cloak class="flex items-center gap-2 text-sm">
                        <span class="text-destructive">Delete it and its files for good?</span>
                        <x-lynx-admin::ui.button variant="destructive" size="sm" wire:click="delete">Yes, delete</x-lynx-admin::ui.button>
                        <x-lynx-admin::ui.button variant="ghost" size="sm" x-on:click="confirming = false">Cancel</x-lynx-admin::ui.button>
                    </span>
                </span>
            @endif
        </div>
    </div>

    <section class="bg-card text-card-foreground divide-y rounded-xl border shadow-sm">
        @foreach ($lines as $line)
            @continue($submission->files->contains('field', $line['name']))
            <div class="grid gap-1 p-5 sm:grid-cols-[14rem_minmax(0,1fr)] sm:gap-6">
                <p class="text-muted-foreground text-sm">{{ $line['label'] }}</p>
                {{-- No whitespace inside: pre-line would show the template's own line breaks. --}}
                @if (filter_var($line['value'], FILTER_VALIDATE_EMAIL))
                    <p class="break-words"><a href="mailto:{{ $line['value'] }}" class="underline">{{ $line['value'] }}</a></p>
                @else
                    <p class="whitespace-pre-line break-words">{{ $line['value'] }}</p>
                @endif
            </div>
        @endforeach
        @if ($submission->files->isNotEmpty())
            <div class="grid gap-1 p-5 sm:grid-cols-[14rem_minmax(0,1fr)] sm:gap-6">
                <p class="text-muted-foreground text-sm">Files</p>
                <ul class="grid gap-1">
                    @foreach ($submission->files as $file)
                        <li><a href="{{ route('lynx.admin.forms.file', [$submission->uuid, $file->id]) }}" class="underline">{{ $file->name }}</a> <span class="text-muted-foreground text-xs">{{ \Illuminate\Support\Number::fileSize($file->bytes) }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    <section class="grid gap-2">
        <label for="submission-notes" class="font-medium">Notes for the team</label>
        <textarea id="submission-notes" rows="3" class="blat-textarea" wire:model="notes" placeholder="Who called back, what was agreed…"></textarea>
        <div><x-lynx-admin::ui.button size="sm" wire:click="saveNotes">Save notes</x-lynx-admin::ui.button></div>
    </section>
</div>
