<form wire:submit="save" class="flex flex-col gap-6">
    @unless ($canEdit)
        <x-lynx-admin::schema-readonly :owner="$owner" />
    @endunless

    <fieldset class="contents" @disabled(! $canEdit)>

    <section class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-5 p-5" aria-labelledby="field-basics">
        <h2 id="field-basics" class="text-base font-semibold">Field</h2>

        <div class="grid gap-1">
            <label for="field-name" class="text-sm font-medium">Name</label>
            @if ($field === null)
                <input id="field-name" type="text" class="blat-input max-w-sm" wire:model="name" autocomplete="off" @error('name') aria-invalid="true" @enderror>
                <p class="text-muted-foreground text-sm">Lowercase letters, digits and underscores, starting with a letter. Templates read it as <code>$page->{{ $name !== '' ? $name : 'name' }}</code>; it cannot change later.</p>
            @else
                <p id="field-name" class="font-mono text-sm">{{ $name }}</p>
            @endif
            @error('name') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-1">
            <label for="field-label" class="text-sm font-medium">Label</label>
            <div class="max-w-sm"><x-lynx-admin::localized-input id="field-label" model="label" placeholder="From the name" /></div>
            @error('label') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-1">
            <label for="field-type" class="text-sm font-medium">Type</label>
            @if ($field === null)
                <select id="field-type" class="blat-select max-w-sm" wire:model.live="type">
                    @foreach ($offeredTypes as $typeName => $info)
                        <option value="{{ $typeName }}">{{ $typeName }}</option>
                    @endforeach
                </select>
            @else
                <p id="field-type" class="text-sm">{{ $type }}</p>
            @endif
            <p class="text-muted-foreground text-sm">{{ $types[$type]['description'] ?? '' }}</p>
            @error('type') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>

        @if ($canTranslate)
            <div class="grid gap-2">
                @if ($field === null)
                    <label class="flex items-center gap-2 text-sm"><input class="blat-checkbox" id="field-translatable" type="checkbox" wire:model.live="translatable"> Translatable: one value per language</label>
                @else
                    <p class="text-sm">{{ $translatable ? 'Translatable: one value per language' : 'Shared by every language' }}</p>
                @endif
                @if ($translatable)
                    <label for="field-fallback" class="text-sm font-medium">When a translation is empty</label>
                    <select id="field-fallback" class="blat-select max-w-sm" wire:model="fallback">
                        <option value="inherit">Show the fallback language</option>
                        <option value="none">Show nothing</option>
                    </select>
                @endif
                @error('translatable') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
            </div>
        @endif

        <div class="grid gap-1">
            <label for="field-description" class="text-sm font-medium">Description</label>
            <x-lynx-admin::localized-input id="field-description" model="description" multiline placeholder="Shown to editors under the field's label" />
            @error('settings.description') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-1">
            <label for="field-notes" class="text-sm font-medium">Notes</label>
            <div class="max-w-xl"><x-lynx-admin::localized-input id="field-notes" model="notes" placeholder="A short hint below the input" /></div>
            @error('settings.notes') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-1">
            @if ($name === 'title')
                <p class="text-sm">Always loaded with the page.</p>
            @else
                <label class="flex items-center gap-2 text-sm font-medium"><input class="blat-checkbox" id="field-autoload" type="checkbox" wire:model="autoload"> Load with the page</label>
                <p class="text-muted-foreground text-sm">For fields almost every list shows. Others load the first time a template reads them, for all listed pages at once.</p>
            @endif
            @error('settings.autoload') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>
    </section>

    @if ($hasItemBuilder)
        @include('lynx-admin::livewire.partials.items.builder')
    @endif

    @if ($definitions !== [])
        <section class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-5 p-5" aria-labelledby="field-settings">
            <h2 id="field-settings" class="text-base font-semibold">Settings for {{ $type }}</h2>
            @foreach ($definitions as $setting => $definition)
                <div class="grid gap-1" wire:key="setting-{{ $type }}-{{ $setting }}">
                    @if ($type === 'choice' && $setting === 'options')
                        <span class="text-sm font-medium">Options</span>
                        <x-lynx-admin::choices-editor model="settings.options" :disabled="! $canEdit" />
                    @elseif ($definition['type'] === 'boolean')
                        <label class="flex items-center gap-2 text-sm font-medium"><input class="blat-checkbox" id="setting-{{ $setting }}" type="checkbox" wire:model="settings.{{ $setting }}"> {{ Str::headline($setting) }}</label>
                    @else
                        <label for="setting-{{ $setting }}" class="text-sm font-medium">{{ Str::headline($setting) }}</label>
                        @if ($definition['enum'] !== [])
                            <select id="setting-{{ $setting }}" class="blat-select max-w-sm" wire:model="settings.{{ $setting }}">
                                <option value="">Default</option>
                                @foreach ($definition['enum'] as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                        @else
                            <input id="setting-{{ $setting }}" type="{{ in_array($definition['type'], ['integer', 'number'], true) ? 'number' : 'text' }}" class="blat-input max-w-sm" wire:model="settings.{{ $setting }}"
                                   @if ($definition['type'] === 'array') placeholder="Comma separated" @endif @error('settings.'.$setting) aria-invalid="true" @enderror>
                        @endif
                    @endif
                    @if ($definition['description'] !== '') <p class="text-muted-foreground text-sm">{{ $definition['description'] }}</p> @endif
                    @error('settings.'.$setting) <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </section>
    @endif

    @if ($availableFormatters !== [])
        <section class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-4 p-5" aria-labelledby="field-formatters">
            <div>
                <h2 id="field-formatters" class="text-base font-semibold">Formatters</h2>
                <p class="text-muted-foreground text-sm">Run in this order when a page is shown. Stored values never change.</p>
            </div>
            @if ($formatters !== [])
                <ol wire:sort="sortFormatter" class="divide-y divide-border rounded-md border border-border">
                    @foreach ($formatters as $formatter)
                        <li wire:key="formatter-{{ $formatter }}" wire:sort:item="{{ $formatter }}" class="flex flex-wrap items-center gap-3 px-3 py-2 text-sm">
                            <span wire:sort:handle class="cursor-grab select-none text-muted-foreground" title="Drag to reorder" aria-hidden="true">⠿</span>
                            <span class="font-medium">{{ $formatter }}</span>
                            <span class="text-muted-foreground">{{ $availableFormatters[$formatter] ?? 'Not installed' }}</span>
                            <button type="button" class="text-primary font-medium underline-offset-4 hover:underline ms-auto" wire:click="removeFormatter(@js($formatter))">Remove</button>
                        </li>
                    @endforeach
                </ol>
            @endif
            <ul class="grid gap-2 text-sm">
                @foreach (array_diff_key($availableFormatters, array_flip($formatters)) as $name => $description)
                    <li class="flex flex-wrap items-baseline gap-3">
                        <x-lynx-admin::ui.button variant="outline" size="sm" wire:click="addFormatter({{ Js::from($name) }})"><x-lucide-plus aria-hidden="true" /> Add {{ $name }}</x-lynx-admin::ui.button>
                        <span class="text-muted-foreground">{{ $description }}</span>
                    </li>
                @endforeach
            </ul>
            @error('settings.formatters') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </section>
    @endif

    @if ($field !== null)
        <section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="field-used-by">
            <h2 id="field-used-by" class="mb-2 text-base font-semibold">Used by</h2>
            <p class="text-sm">{{ $usedBy === [] ? 'No template uses this field yet.' : implode(', ', $usedBy) }}</p>
        </section>
    @endif

    </fieldset>

    <div class="flex flex-wrap items-center gap-3">
        @if ($canEdit)
            <x-lynx-admin::ui.button type="submit">{{ $field === null ? 'Create field' : 'Save' }}</x-lynx-admin::ui.button>
        @endif
        <x-lynx-admin::ui.button variant="outline" :href="route('lynx.admin.fields')">Back to fields</x-lynx-admin::ui.button>
        @if ($canEdit && $field !== null && ! $isConfirmingDelete)
            <x-lynx-admin::ui.button variant="outline" type="button" wire:click="confirmDelete" class="ms-auto text-destructive hover:text-destructive">Delete field</x-lynx-admin::ui.button>
        @endif
    </div>

    @if ($isConfirmingDelete)
        <section role="alert" class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-3 p-5" aria-labelledby="field-delete">
            <h2 id="field-delete" class="text-base font-semibold text-destructive">Delete {{ $field }}?</h2>
            <ul class="list-disc ps-5 text-sm">
                @foreach ($deleteSummary as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
            <p class="text-muted-foreground text-sm">The stored values cannot be restored. Code that reads <code>$page->{{ $field }}</code> will get nothing.</p>
            <div class="flex flex-wrap gap-3">
                <x-lynx-admin::ui.button variant="destructive" type="button" wire:click="delete">Delete field {{ $field }}</x-lynx-admin::ui.button>
                <x-lynx-admin::ui.button variant="outline" type="button" wire:click="cancelDelete">Cancel</x-lynx-admin::ui.button>
            </div>
        </section>
    @endif
</form>
