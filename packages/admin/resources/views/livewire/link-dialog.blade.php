{{-- The rich text link dialog (LinkDialog). Opened by a rich text editor; answers with lynx-link-chosen. --}}
<div>
    <x-lynx-admin::ui.dialog id="lynx-link">
        <x-lynx-admin::ui.dialog-content class="max-h-[90vh] overflow-y-auto sm:max-w-xl" :close-on-overlay="false">
            <x-lynx-admin::ui.dialog-header>
                <x-lynx-admin::ui.dialog-title>Link</x-lynx-admin::ui.dialog-title>
            </x-lynx-admin::ui.dialog-header>

            <form wire:submit="insert" class="grid gap-4">
                <x-lynx-admin::ui.tabs value="link" x-on:open-dialog-lynx-link.window="tab = 'link'">
                    <x-lynx-admin::ui.tabs-list aria-label="Link settings">
                        <x-lynx-admin::ui.tabs-trigger value="link">Link</x-lynx-admin::ui.tabs-trigger>
                        <x-lynx-admin::ui.tabs-trigger value="attributes">Attributes</x-lynx-admin::ui.tabs-trigger>
                    </x-lynx-admin::ui.tabs-list>

                    {{-- An address refused while Attributes is open: show the tab with the error. --}}
                    @error('href') <span hidden x-init="tab = 'link'"></span> @enderror
                    <x-lynx-admin::ui.tabs-content value="link" class="grid gap-4 pt-2">
                        <div class="grid gap-1.5">
                            <label for="link-text" class="text-sm font-medium">Link text</label>
                            <input id="link-text" type="text" class="blat-input" wire:model="text" autocomplete="off">
                        </div>

                        <div class="grid gap-1.5">
                            <label for="link-href" class="text-sm font-medium">Link to</label>
                            <p id="link-href-help" class="text-muted-foreground text-sm">An address, an email (mailto:), an #anchor, or words to find a page.</p>
                            <input id="link-href" type="text" class="blat-input" wire:model.live.debounce.300ms="href" autocomplete="off"
                                   aria-describedby="link-href-help" @error('href') aria-invalid="true" @enderror>
                            @error('href') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                            @if ($matches !== [])
                                <ul class="bg-card rounded-lg border divide-y divide-border overflow-hidden" aria-label="Matching pages">
                                    @foreach ($matches as $page)
                                        <li>
                                            <button type="button" wire:click="choosePage({{ $page['id'] }})" class="flex w-full items-center justify-between gap-4 px-3 py-2 text-start text-sm hover:bg-accent hover:text-accent-foreground">
                                                <span>{{ $page['title'] }}</span>
                                                <span class="text-muted-foreground truncate">{{ $page['url'] }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <div class="grid gap-2 border-t pt-3">
                            <button type="button" wire:click="show('pages')" class="text-muted-foreground hover:text-foreground flex items-center gap-2 text-sm font-medium" aria-expanded="{{ $panel === 'pages' ? 'true' : 'false' }}">
                                <x-lucide-network class="size-4" aria-hidden="true" /> Select page…
                            </button>
                            @if ($browsing !== null)
                                @include('lynx-admin::livewire.link-dialog-pages')
                            @endif
                            <button type="button" wire:click="show('anchors')" class="text-muted-foreground hover:text-foreground flex items-center gap-2 text-sm font-medium" aria-expanded="{{ $panel === 'anchors' ? 'true' : 'false' }}">
                                <x-lucide-hash class="size-4" aria-hidden="true" /> Select section…
                            </button>
                            @if ($panel === 'anchors')
                                <ul class="max-h-56 overflow-y-auto rounded-lg border divide-y divide-border text-sm" aria-label="Sections of {{ $chosenTitle !== '' ? $chosenTitle : 'this page' }}">
                                    @forelse ($anchors as $anchor)
                                        <li>
                                            <button type="button" wire:click="chooseAnchor(@js($anchor['id']))" class="flex w-full items-center justify-between gap-4 px-3 py-2 text-start hover:bg-accent">
                                                <span>{{ $anchor['label'] !== '' ? $anchor['label'] : $anchor['id'] }}</span>
                                                <span class="text-muted-foreground font-mono text-xs">#{{ $anchor['id'] }}</span>
                                            </button>
                                        </li>
                                    @empty
                                        <li class="text-muted-foreground px-3 py-2">No sections: headings get one when their field uses the heading anchors formatter.</li>
                                    @endforelse
                                </ul>
                            @endif
                            <button type="button" wire:click="show('files')" class="text-muted-foreground hover:text-foreground flex items-center gap-2 text-sm font-medium" aria-expanded="{{ $panel === 'files' ? 'true' : 'false' }}">
                                <x-lucide-file-text class="size-4" aria-hidden="true" /> Select file…
                            </button>
                            @if ($panel === 'files')
                                <ul class="max-h-56 overflow-y-auto rounded-lg border divide-y divide-border text-sm" aria-label="Files of this page">
                                    @forelse ($files as $index => $file)
                                        <li><button type="button" wire:click="chooseFile({{ $index }})" class="w-full px-3 py-2 text-start hover:bg-accent">{{ $file['name'] }}</button></li>
                                    @empty
                                        <li class="text-muted-foreground px-3 py-2">This page has no files yet.</li>
                                    @endforelse
                                </ul>
                                @if ($chosenFiles !== [])
                                    <ul class="max-h-56 overflow-y-auto rounded-lg border divide-y divide-border text-sm" aria-label="Files of {{ $chosenTitle }}">
                                        <li class="bg-muted/50 px-3 py-1.5 text-xs font-medium">Files of {{ $chosenTitle }}</li>
                                        @foreach ($chosenFiles as $index => $file)
                                            <li><button type="button" wire:click="chooseFile({{ $index }}, true)" class="w-full px-3 py-2 text-start hover:bg-accent">{{ $file['name'] }}</button></li>
                                        @endforeach
                                    </ul>
                                @endif
                            @endif
                        </div>
                    </x-lynx-admin::ui.tabs-content>

                    <x-lynx-admin::ui.tabs-content value="attributes" class="grid gap-4 pt-2">
                        <div class="grid gap-1.5">
                            <label for="link-title" class="text-sm font-medium">Title</label>
                            <p id="link-title-help" class="text-muted-foreground text-sm">Extra text describing the link, shown on hover.</p>
                            <input id="link-title" type="text" class="blat-input" wire:model="title" aria-describedby="link-title-help">
                        </div>
                        <div class="grid items-start gap-4 sm:grid-cols-2">
                            @if ($targets !== [])
                                <div class="grid content-start gap-1.5">
                                    <label for="link-target" class="text-sm font-medium">Opens in</label>
                                    <select id="link-target" class="blat-select" wire:model="target" @error('target') aria-invalid="true" @enderror>
                                        <option value="">This window</option>
                                        @foreach ($targets as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('target') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                                </div>
                            @endif
                            @if ($rels !== [])
                                <fieldset class="grid gap-1.5">
                                    <legend class="mb-1 text-sm font-medium">Relation</legend>
                                    @foreach ($rels as $value => $label)
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" class="blat-checkbox" value="{{ $value }}" wire:model="rel"> {{ $label }}
                                        </label>
                                    @endforeach
                                    @error('rel.*') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                                </fieldset>
                            @endif
                        </div>
                    </x-lynx-admin::ui.tabs-content>
                </x-lynx-admin::ui.tabs>

                <x-lynx-admin::ui.dialog-footer>
                    @if ($isEditing)
                        <x-lynx-admin::ui.button type="button" variant="ghost" wire:click="unlink" class="sm:me-auto">Remove link</x-lynx-admin::ui.button>
                    @endif
                    <x-lynx-admin::ui.button type="button" variant="outline" x-on:click="open = false">Cancel</x-lynx-admin::ui.button>
                    <x-lynx-admin::ui.button type="submit">Insert link</x-lynx-admin::ui.button>
                </x-lynx-admin::ui.dialog-footer>
            </form>
        </x-lynx-admin::ui.dialog-content>
    </x-lynx-admin::ui.dialog>
</div>
