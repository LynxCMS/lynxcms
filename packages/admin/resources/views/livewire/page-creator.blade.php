<form wire:submit="create" class="space-y-6" aria-labelledby="new-page-title">
    <x-lynx-admin::ui.breadcrumb>
        <x-lynx-admin::ui.breadcrumb-list>
            <x-lynx-admin::ui.breadcrumb-item><x-lynx-admin::ui.breadcrumb-link :href="route('lynx.admin.pages')">Pages</x-lynx-admin::ui.breadcrumb-link></x-lynx-admin::ui.breadcrumb-item>
            <x-lynx-admin::ui.breadcrumb-separator />
            <x-lynx-admin::ui.breadcrumb-item><x-lynx-admin::ui.breadcrumb-link :href="route('lynx.admin.pages.edit', $parentId)">{{ $parentTitle }}</x-lynx-admin::ui.breadcrumb-link></x-lynx-admin::ui.breadcrumb-item>
        </x-lynx-admin::ui.breadcrumb-list>
    </x-lynx-admin::ui.breadcrumb>

    <h1 id="new-page-title" class="text-2xl font-semibold tracking-tight">New page under {{ $parentTitle }}</h1>

    @foreach (['template', 'parent', 'page'] as $key)
        @error($key) <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
    @endforeach

    @if ($choices === [])
        {{-- Family rules (ADR-0032) or singletons leave nothing to create here. --}}
        <p role="status" class="bg-card text-card-foreground rounded-xl border shadow-sm p-5 text-sm">No template can be used under {{ $parentTitle }}. Its template's family settings decide which pages it may have.</p>
    @else
    <section class="@container bg-card text-card-foreground rounded-xl border shadow-sm divide-y divide-border">
        <div class="grid gap-2 p-5 sm:grid-cols-[12rem_minmax(0,1fr)] sm:gap-6">
            <label for="new-page-template" class="font-medium">Template</label>
            <select id="new-page-template" class="blat-select max-w-sm" wire:model.live="template">
                @foreach ($choices as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @foreach ($fields as $field)
            {{-- Live inputs, so the URL preview follows the title as it is typed. --}}
            @continue(! ($states[$field->name]['shown'] ?? true))
            <x-lynx-admin::editor.field :field="$field" :languages="$languages" :input="$inputs->for($field->type)" :live="true" :current="$values[$field->name] ?? []" :required="$states[$field->name]['required'] ?? null" :show-type="$isSuperuser" />
        @endforeach

        @if ($addLater !== [])
            <p role="note" class="text-muted-foreground p-5 text-sm">Add {{ implode(', ', $addLater) }} once the page exists: files are stored with the page. It is created unpublished until then.</p>
        @endif

        <div class="grid gap-2 p-5 sm:grid-cols-[12rem_minmax(0,1fr)] sm:gap-6">
            <div>
                <label for="new-page-name" class="font-medium">URL name</label>
                <p class="text-muted-foreground text-sm">Optional: made from the title.</p>
            </div>
            <div>
                <input id="new-page-name" type="text" class="blat-input max-w-sm" wire:model.live.debounce.300ms="name" placeholder="from the title" @error('names.'.$defaultCode) aria-invalid="true" @enderror>
                @if ($url)
                    <p class="text-muted-foreground text-sm mt-1">URL: <span class="text-foreground">{{ $url }}</span></p>
                @endif
                {{-- Core keys name errors by language code, e.g. names.en. --}}
                @error('names.'.$defaultCode) <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <div class="flex flex-wrap items-center gap-4">
        <x-lynx-admin::ui.button type="submit">Create page</x-lynx-admin::ui.button>
        @if ($addLater === [])
            <label class="flex items-center gap-2 text-sm"><input class="blat-checkbox" type="checkbox" wire:model="publish"> Publish now</label>
        @endif
        <span class="text-muted-foreground text-sm">Unpublished pages are only visible in the admin.</span>
    </div>
    @endif
</form>
