{{-- The Field tab: what the field holds, its name, languages and help, its kind's settings, then Advanced and Delete. --}}
@php($section = 'grid gap-5 border-t pt-6 first:border-t-0 first:pt-0')
<div class="grid max-w-3xl gap-8">
    @include('lynx-admin::livewire.partials.field.kind')

    {{-- On a new field, the name follows the default language's label until it is typed in. --}}
    <section class="{{ $section }}" aria-labelledby="field-name-heading" @if ($field === null) x-data="{ typed: false }" x-on:input="if ($event.target.id === 'field-label' && ! typed) $wire.name = lynxFieldName($event.target.value)" @endif>
        <h2 id="field-name-heading" class="text-base font-semibold">Name</h2>
        <div class="grid gap-1.5">
            <label for="field-label" class="text-sm font-medium">Label</label>
            <x-lynx-admin::localized-input id="field-label" model="label" :placeholder="$field === null ? 'Such as Opening hours' : $field" />
            <p class="text-muted-foreground text-sm">What editors read above the input.</p>
            @error('label') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-1.5">
            <label for="field-name" class="text-sm font-medium">Name in templates</label>
            @if ($field === null)
                                <div class="bg-muted/50 flex max-w-md items-stretch overflow-hidden rounded-md border font-mono text-sm focus-within:ring-[3px] focus-within:ring-ring/50">
                    <span class="text-muted-foreground grid place-items-center px-2.5">$page-&gt;</span>
                    <input id="field-name" type="text" class="bg-background min-w-0 flex-1 border-s px-2.5 py-2 outline-none" wire:model="name" x-on:input="typed = true" autocomplete="off" spellcheck="false" @error('name') aria-invalid="true" @enderror>
                </div>
                <p class="text-muted-foreground text-sm">Made from the label. Lowercase letters, digits and underscores; it can't change once the field exists.</p>
            @else
                <p id="field-name" class="flex items-center gap-1.5 font-mono text-sm"><x-lucide-lock class="text-muted-foreground size-3.5" aria-hidden="true" />$page-&gt;{{ $name }}</p>
            @endif
            @error('name') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </div>
    </section>

    @if ($canTranslate)
        <section class="{{ $section }}" aria-labelledby="field-languages-heading">
            <h2 id="field-languages-heading" class="text-base font-semibold">Languages</h2>
            @if ($field === null)
                <div class="grid gap-2" role="radiogroup" aria-labelledby="field-languages-heading">
                    @foreach ([true => ['Its own text in each language', 'Every language gets a value of its own.'], false => ['The same in every language', 'For numbers, dates and codes.']] as $value => [$title, $hint])
                        <label class="has-checked:border-primary has-checked:bg-primary/5 flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm">
                            <input type="radio" class="mt-0.5 accent-primary" name="field-translatable" wire:click="$set('translatable', {{ $value ? 'true' : 'false' }})" @checked($translatable === (bool) $value)>
                            <span class="grid gap-0.5"><span class="font-medium">{{ $title }}</span><span class="text-muted-foreground">{{ $hint }}</span></span>
                        </label>
                    @endforeach
                </div>
            @else
                <p class="text-sm">{{ $translatable ? 'Its own text in each language.' : 'The same in every language.' }}</p>
            @endif
            @if ($translatable)
                <div class="flex flex-wrap items-center gap-3">
                    <label for="field-fallback" class="text-sm font-medium">When a language has no text</label>
                    <select id="field-fallback" class="blat-select w-auto" wire:model="fallback">
                        <option value="inherit">Show the fallback language's</option>
                        <option value="none">Show nothing</option>
                    </select>
                </div>
            @endif
            @error('translatable') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        </section>
    @endif

    <section class="{{ $section }}" aria-labelledby="field-help-heading">
        <h2 id="field-help-heading" class="text-base font-semibold">Help for editors</h2>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="grid content-start gap-1.5">
                <label for="field-description" class="text-sm font-medium">Above the input</label>
                <x-lynx-admin::localized-input id="field-description" model="description" multiline placeholder="Optional" />
                @error('settings.description') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
            </div>
            <div class="grid content-start gap-1.5">
                <label for="field-notes" class="text-sm font-medium">Below the input</label>
                <x-lynx-admin::localized-input id="field-notes" model="notes" multiline placeholder="Optional" />
                @error('settings.notes') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    @include('lynx-admin::livewire.partials.field.settings')

    <details class="{{ $section }} group" @error('settings.autoload') open @enderror>
        <summary class="w-fit cursor-pointer text-base font-semibold">Advanced</summary>
        @if ($name === 'title')
            <p class="text-sm">Always loaded with the page.</p>
        @else
            <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm">
                <input class="blat-checkbox mt-0.5" id="field-autoload" type="checkbox" wire:model="autoload">
                <span class="grid gap-0.5"><span class="font-medium">Load with the page</span><span class="text-muted-foreground">For fields almost every list shows. Others load the first time a template reads them, for all listed pages at once.</span></span>
            </label>
        @endif
        @error('settings.autoload') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
    </details>

    @include('lynx-admin::livewire.partials.field.danger')
</div>
