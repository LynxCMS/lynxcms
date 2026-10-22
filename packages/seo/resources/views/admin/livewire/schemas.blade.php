<div class="space-y-4">
    <p class="text-muted-foreground max-w-prose text-sm">Describe a template's pages to search engines as a schema.org type, such as an event or a shop. For each property, pick a field, a built-in such as <code>@title</code>, or write a fixed value after an equals sign, such as <code>=EUR</code>. Every page also gets the site, the page and its breadcrumbs.</p>

    @if ($editing === null)
        <x-lynx-admin::ui.table variant="card">
            <x-lynx-admin::ui.table-header>
                <x-lynx-admin::ui.table-row class="hover:bg-transparent">
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">Template</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">Type</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4"><span class="sr-only">Actions</span></x-lynx-admin::ui.table-head>
                </x-lynx-admin::ui.table-row>
            </x-lynx-admin::ui.table-header>
            <x-lynx-admin::ui.table-body>
                @forelse ($mappings as $index => $mapping)
                    <x-lynx-admin::ui.table-row wire:key="seo-mapping-{{ $index }}">
                        <x-lynx-admin::ui.table-cell class="px-4 py-2 font-medium">{{ $templates[$mapping['template']] ?? $mapping['template'] }}</x-lynx-admin::ui.table-cell>
                        <x-lynx-admin::ui.table-cell class="px-4 py-2">{{ $mapping['type'] }}</x-lynx-admin::ui.table-cell>
                        <x-lynx-admin::ui.table-cell class="px-4 py-2 text-end">
                            <span class="inline-flex gap-2">
                                <x-lynx-admin::ui.button variant="outline" size="xs" wire:click="edit({{ $index }})">Edit</x-lynx-admin::ui.button>
                                <x-lynx-admin::ui.button variant="ghost" size="xs" wire:click="remove({{ $index }})" wire:confirm="Remove the structured data of this template?">Remove</x-lynx-admin::ui.button>
                            </span>
                        </x-lynx-admin::ui.table-cell>
                    </x-lynx-admin::ui.table-row>
                @empty
                    <x-lynx-admin::ui.table-row><x-lynx-admin::ui.table-cell colspan="3" class="whitespace-normal px-4 py-6 text-center text-muted-foreground">No template is mapped yet.</x-lynx-admin::ui.table-cell></x-lynx-admin::ui.table-row>
                @endforelse
            </x-lynx-admin::ui.table-body>
        </x-lynx-admin::ui.table>
        @error('mapping') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        <x-lynx-admin::ui.button wire:click="create">Map a template</x-lynx-admin::ui.button>
    @else
        <form wire:submit="save" class="bg-card grid max-w-3xl gap-4 rounded-xl border p-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1.5 text-sm">
                    <span class="font-medium">Template</span>
                    <select class="blat-select" wire:model.live="template">
                        <option value="">Choose…</option>
                        @foreach ($templates as $name => $label)
                            <option value="{{ $name }}">{{ $label }} ({{ $name }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-1.5 text-sm">
                    <span class="font-medium">Schema.org type</span>
                    <select class="blat-select" wire:model.live="type">
                        <option value="">Choose…</option>
                        @foreach ($types as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            @error('mapping')
                <ul role="alert" class="text-destructive grid gap-1 text-sm">
                    @foreach ($errors->get('mapping') as $problem) <li>{{ $problem }}</li> @endforeach
                </ul>
            @enderror

            @if ($properties !== [])
                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">Properties</legend>
                    @foreach ($properties as $row)
                        <div wire:key="seo-property-{{ $row['key'] }}" class="grid items-center gap-1 sm:grid-cols-[14rem_1fr] sm:gap-3">
                            <label for="seo-source-{{ $row['key'] }}" class="font-mono text-xs">{{ $row['property'] }} <span class="text-muted-foreground">({{ $row['kind'] }})</span></label>
                            <input id="seo-source-{{ $row['key'] }}" type="text" class="blat-input" list="seo-suggestions-{{ $row['key'] }}" wire:model.blur="sources.{{ $row['key'] }}" autocomplete="off">
                            <datalist id="seo-suggestions-{{ $row['key'] }}">
                                @foreach ($row['suggestions'] as $suggestion)
                                    <option value="{{ $suggestion }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                    @endforeach
                </fieldset>
            @endif

            <div class="flex gap-2">
                <x-lynx-admin::ui.button type="submit">Save</x-lynx-admin::ui.button>
                <x-lynx-admin::ui.button type="button" variant="outline" wire:click="cancel">Cancel</x-lynx-admin::ui.button>
            </div>
        </form>
    @endif
</div>
