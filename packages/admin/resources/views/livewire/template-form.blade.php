<div class="space-y-6">
    @unless ($canEdit)
        <x-lynx-admin::schema-readonly :owner="$owner" />
    @endunless

    <fieldset class="contents space-y-6" @disabled(! $canEdit)>

    @if ($template === null)
        <form wire:submit="create" class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-5 p-5">
            <div class="grid gap-1">
                <label for="template-name" class="text-sm font-medium">Name</label>
                <input id="template-name" type="text" class="blat-input max-w-sm" wire:model="name" autocomplete="off" @error('name') aria-invalid="true" @enderror>
                <p class="text-muted-foreground text-sm">Lowercase letters, digits, dashes and underscores. Lynx renders pages of this template with <code>templates/{{ $name !== '' ? $name : 'name' }}.blade.php</code>; it cannot change later.</p>
                @error('name') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-1">
                <label for="template-label" class="text-sm font-medium">Label</label>
                <div class="max-w-sm"><x-lynx-admin::localized-input id="template-label" model="label" placeholder="From the name" /></div>
                @error('label') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-1">
                <label for="template-from" class="text-sm font-medium">Copy fields from</label>
                <select id="template-from" class="blat-select max-w-sm" wire:model="from">
                    <option value="">No template, start empty</option>
                    @foreach ($templates as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                @error('from') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
            </div>
            <div><x-lynx-admin::ui.button type="submit">Create template</x-lynx-admin::ui.button></div>
        </form>
    @else
        <form wire:submit="saveLabel" class="bg-card text-card-foreground rounded-xl border shadow-sm flex flex-wrap items-end gap-3 p-5">
            <div class="grid gap-1">
                <label for="template-label" class="text-sm font-medium">Label</label>
                <div class="w-72"><x-lynx-admin::localized-input id="template-label" model="label" /></div>
            </div>
            @if ($canEdit) <x-lynx-admin::ui.button variant="outline" type="submit">Save label</x-lynx-admin::ui.button> @endif
            @error('label') <p role="alert" class="text-destructive mt-1 text-sm w-full">{{ $message }}</p> @enderror
        </form>

        <section class="bg-card text-card-foreground rounded-xl border shadow-sm" aria-labelledby="template-fields">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border p-5">
                <h2 id="template-fields" class="text-base font-semibold">Fields</h2>
                @if ($canEdit) <p class="text-muted-foreground text-sm">Drag fields and sections between tabs, columns and sections, or use Move to. Overrides apply to this template only.</p> @endif
            </div>

            @error('fields') <p role="alert" class="text-destructive mt-1 text-sm px-5 pt-4">{{ $message }}</p> @enderror

            @include('lynx-admin::livewire.partials.template-layout')

            @if ($canEdit)
            <div class="flex flex-wrap items-end gap-3 border-t border-border p-5">
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Add a field</span>
                    <select id="template-new-field" class="blat-select w-56" wire:model="newField">
                        <option value="">Choose a field</option>
                        @foreach ($available as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <x-lynx-admin::ui.button variant="outline" type="button" wire:click="addField">Add</x-lynx-admin::ui.button>
                <a href="{{ route('lynx.admin.fields.create') }}" class="text-primary font-medium underline-offset-4 hover:underline text-sm">New field</a>
            </div>
            @endif

            @if ($removed !== [])
                <p class="text-muted-foreground text-sm px-5">Values of removed fields are kept on the pages (as orphans) until purged, so adding a field back restores them: {{ implode(', ', $removed) }}.</p>
            @endif

            @if ($canEdit)
                <div class="p-5"><x-lynx-admin::ui.button type="button" wire:click="saveFields">Save fields</x-lynx-admin::ui.button></div>
            @endif
        </section>

        @include('lynx-admin::livewire.template-family')
    @endif
    </fieldset>
</div>
