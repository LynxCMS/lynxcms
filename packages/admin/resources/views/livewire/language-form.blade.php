<form wire:submit="save" class="flex flex-col gap-6">
    @unless ($canEdit)
        <x-lynx-admin::schema-readonly />
    @endunless

    <fieldset class="contents" @disabled(! $canEdit)>

    <section class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-5 p-5" aria-labelledby="language-basics">
        <h2 id="language-basics" class="text-base font-semibold">Language</h2>

        <div class="grid gap-1">
            <label for="language-code" class="text-sm font-medium">Code</label>
            <input id="language-code" type="text" class="blat-input max-w-xs font-mono" wire:model="code" autocomplete="off" @error('code') aria-invalid="true" @enderror>
            <p class="text-muted-foreground text-sm">Lowercase letters, digits, "-" and "_", e.g. <code>pt</code> or <code>pt-br</code>. Code and the API refer to the language by it.</p>
            @error('code') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-1">
            <label for="language-name" class="text-sm font-medium">Name</label>
            <input id="language-name" type="text" class="blat-input max-w-sm" wire:model="name" placeholder="Português" @error('name') aria-invalid="true" @enderror>
            @error('name') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-1">
            <label for="language-label" class="text-sm font-medium">Tab label</label>
            <input id="language-label" type="text" class="blat-input max-w-xs" wire:model="label" maxlength="32" placeholder="{{ $name !== '' ? $name : 'From the name' }}">
            <p class="text-muted-foreground text-sm">What the editor's language tabs show: the code, an abbreviation or anything short. Empty shows the name.</p>
            @error('label') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-1">
            <label for="language-locale" class="text-sm font-medium">Locale</label>
            <input id="language-locale" type="text" class="blat-input max-w-xs font-mono" wire:model="locale" placeholder="{{ $code !== '' ? $code : 'pt-PT' }}">
            <p class="text-muted-foreground text-sm">For Laravel and the HTML <code>lang</code> attribute. Empty uses the code.</p>
            @error('locale') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-1">
            <label for="language-prefix" class="text-sm font-medium">URL prefix</label>
            <input id="language-prefix" type="text" class="blat-input max-w-xs font-mono" wire:model="prefix" placeholder="{{ $language === null ? ($code !== '' ? $code : 'pt') : '' }}">
            <p class="text-muted-foreground text-sm">{{ $language === null ? 'Empty uses the code.' : 'Empty means no prefix. Changing it rebuilds this language\'s URLs; the old ones redirect.' }}</p>
            @error('prefix') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>

        @unless ($isDefault)
            <div class="grid gap-1">
                <label for="language-fallback" class="text-sm font-medium">Falls back to</label>
                <select id="language-fallback" class="blat-select max-w-sm" wire:model="fallback">
                    <option value="">The default language</option>
                    @foreach ($fallbacks as $fallbackCode => $fallbackName)
                        <option value="{{ $fallbackCode }}">{{ $fallbackName }} ({{ $fallbackCode }})</option>
                    @endforeach
                </select>
                <p class="text-muted-foreground text-sm">Shown when a value is missing in this language.</p>
                @error('fallback') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
            </div>
        @endunless

        <label class="flex items-center gap-2 text-sm"><input id="language-active" class="blat-checkbox" type="checkbox" wire:model="active"> Active: visible on the site</label>
    </section>
    </fieldset>

    <div class="flex flex-wrap items-center gap-3">
        @if ($canEdit)
            <x-lynx-admin::ui.button type="submit">{{ $language === null ? 'Create language' : 'Save' }}</x-lynx-admin::ui.button>
        @endif
        <x-lynx-admin::ui.button variant="outline" :href="route('lynx.admin.languages')">Back to languages</x-lynx-admin::ui.button>
        @if ($canEdit && $language !== null && ! $isDefault && ! $isConfirmingDelete)
            <x-lynx-admin::ui.button variant="outline" type="button" wire:click="confirmDelete" class="ms-auto text-destructive hover:text-destructive">Delete language</x-lynx-admin::ui.button>
        @endif
    </div>

    @if ($isConfirmingDelete)
        <section role="alert" class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-3 p-5" aria-labelledby="language-delete">
            <h2 id="language-delete" class="text-base font-semibold text-destructive">Delete {{ $language }}?</h2>
            <ul class="list-disc ps-5 text-sm">
                @foreach ($deleteSummary as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
            <p class="text-muted-foreground text-sm">Its translations, page names and URLs cannot be restored. Languages that fell back to it fall back to the default language.</p>
            <div class="flex flex-wrap gap-3">
                <x-lynx-admin::ui.button variant="destructive" type="button" wire:click="delete">Delete language {{ $language }}</x-lynx-admin::ui.button>
                <x-lynx-admin::ui.button variant="outline" type="button" wire:click="cancelDelete">Cancel</x-lynx-admin::ui.button>
            </div>
        </section>
    @endif
</form>
