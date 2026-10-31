{{-- The rich text link dialog (LinkDialog). Opened by a rich text editor; answers with lynx-link-chosen. --}}
<div>
    <x-lynx-admin::ui.dialog id="lynx-link">
        <x-lynx-admin::ui.dialog-content class="max-h-[90vh] overflow-y-auto sm:max-w-xl" :close-on-overlay="false">
            <x-lynx-admin::ui.dialog-header>
                <x-lynx-admin::ui.dialog-title>{{ __('lynx-admin::link.title') }}</x-lynx-admin::ui.dialog-title>
            </x-lynx-admin::ui.dialog-header>

            <form wire:submit="insert" class="grid gap-4">
                <x-lynx-admin::ui.tabs value="link" x-on:open-dialog-lynx-link.window="tab = 'link'">
                    <x-lynx-admin::ui.tabs-list aria-label="{{ __('lynx-admin::link.settings') }}">
                        <x-lynx-admin::ui.tabs-trigger value="link">{{ __('lynx-admin::link.tab_link') }}</x-lynx-admin::ui.tabs-trigger>
                        <x-lynx-admin::ui.tabs-trigger value="attributes">{{ __('lynx-admin::link.tab_attributes') }}</x-lynx-admin::ui.tabs-trigger>
                    </x-lynx-admin::ui.tabs-list>

                    {{-- An address refused while Attributes is open: show the tab with the error. --}}
                    @error('href') <span hidden x-init="tab = 'link'"></span> @enderror
                    <x-lynx-admin::ui.tabs-content value="link" class="grid gap-4 pt-2">
                        <div class="grid gap-1.5">
                            <label for="link-text" class="text-sm font-medium">{{ __('lynx-admin::link.text') }}</label>
                            <input id="link-text" type="text" class="blat-input" wire:model="text" autocomplete="off">
                        </div>

                        <div class="grid gap-1.5">
                            <label for="link-href" class="text-sm font-medium">{{ __('lynx-admin::link.to') }}</label>
                            <p id="link-href-help" class="text-muted-foreground text-sm">{{ __('lynx-admin::link.to_help') }}</p>
                            <input id="link-href" type="text" class="blat-input" wire:model.live.debounce.300ms="href" autocomplete="off"
                                   aria-describedby="link-href-help" @error('href') aria-invalid="true" @enderror>
                            @error('href') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                            @if ($matches !== [])
                                <ul class="bg-card rounded-lg border divide-y divide-border overflow-hidden" aria-label="{{ __('lynx-admin::link.matching_pages') }}">
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
                                <x-lucide-network class="size-4" aria-hidden="true" /> {{ __('lynx-admin::link.select_page') }}
                            </button>
                            @if ($browsing !== null)
                                @include('lynx-admin::livewire.link-dialog-pages')
                            @endif
                            <button type="button" wire:click="show('anchors')" class="text-muted-foreground hover:text-foreground flex items-center gap-2 text-sm font-medium" aria-expanded="{{ $panel === 'anchors' ? 'true' : 'false' }}">
                                <x-lucide-hash class="size-4" aria-hidden="true" /> {{ __('lynx-admin::link.select_section') }}
                            </button>
                            @if ($panel === 'anchors')
                                <ul class="max-h-56 overflow-y-auto rounded-lg border divide-y divide-border text-sm" aria-label="{{ $chosenTitle !== '' ? __('lynx-admin::link.sections_of', ['page' => $chosenTitle]) : __('lynx-admin::link.sections_of_this_page') }}">
                                    @forelse ($anchors as $anchor)
                                        <li>
                                            <button type="button" wire:click="chooseAnchor(@js($anchor['id']))" class="flex w-full items-center justify-between gap-4 px-3 py-2 text-start hover:bg-accent">
                                                <span>{{ $anchor['label'] !== '' ? $anchor['label'] : $anchor['id'] }}</span>
                                                <span class="text-muted-foreground font-mono text-xs">#{{ $anchor['id'] }}</span>
                                            </button>
                                        </li>
                                    @empty
                                        <li class="text-muted-foreground px-3 py-2">{{ __('lynx-admin::link.no_sections') }}</li>
                                    @endforelse
                                </ul>
                            @endif
                            <button type="button" wire:click="show('files')" class="text-muted-foreground hover:text-foreground flex items-center gap-2 text-sm font-medium" aria-expanded="{{ $panel === 'files' ? 'true' : 'false' }}">
                                <x-lucide-file-text class="size-4" aria-hidden="true" /> {{ __('lynx-admin::link.select_file') }}
                            </button>
                            @if ($panel === 'files')
                                <ul class="max-h-56 overflow-y-auto rounded-lg border divide-y divide-border text-sm" aria-label="{{ __('lynx-admin::link.files_of_this_page') }}">
                                    @forelse ($files as $index => $file)
                                        <li><button type="button" wire:click="chooseFile({{ $index }})" class="w-full px-3 py-2 text-start hover:bg-accent">{{ $file['name'] }}</button></li>
                                    @empty
                                        <li class="text-muted-foreground px-3 py-2">{{ __('lynx-admin::link.no_files') }}</li>
                                    @endforelse
                                </ul>
                                @if ($chosenFiles !== [])
                                    <ul class="max-h-56 overflow-y-auto rounded-lg border divide-y divide-border text-sm" aria-label="{{ __('lynx-admin::link.files_of', ['page' => $chosenTitle]) }}">
                                        <li class="bg-muted/50 px-3 py-1.5 text-xs font-medium">{{ __('lynx-admin::link.files_of', ['page' => $chosenTitle]) }}</li>
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
                            <label for="link-title" class="text-sm font-medium">{{ __('lynx-admin::link.link_title') }}</label>
                            <p id="link-title-help" class="text-muted-foreground text-sm">{{ __('lynx-admin::link.link_title_help') }}</p>
                            <input id="link-title" type="text" class="blat-input" wire:model="title" aria-describedby="link-title-help">
                        </div>
                        <div class="grid items-start gap-4 sm:grid-cols-2">
                            @if ($targets !== [])
                                <div class="grid content-start gap-1.5">
                                    <label for="link-target" class="text-sm font-medium">{{ __('lynx-admin::link.opens_in') }}</label>
                                    <select id="link-target" class="blat-select" wire:model="target" @error('target') aria-invalid="true" @enderror>
                                        <option value="">{{ __('lynx-admin::link.this_window') }}</option>
                                        @foreach ($targets as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('target') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                                </div>
                            @endif
                            @if ($rels !== [])
                                <fieldset class="grid gap-1.5">
                                    <legend class="mb-1 text-sm font-medium">{{ __('lynx-admin::link.relation') }}</legend>
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
                        <x-lynx-admin::ui.button type="button" variant="ghost" wire:click="unlink" class="sm:me-auto">{{ __('lynx-admin::link.remove') }}</x-lynx-admin::ui.button>
                    @endif
                    <x-lynx-admin::ui.button type="button" variant="outline" x-on:click="open = false">{{ __('lynx-admin::link.cancel') }}</x-lynx-admin::ui.button>
                    <x-lynx-admin::ui.button type="submit">{{ __('lynx-admin::link.insert') }}</x-lynx-admin::ui.button>
                </x-lynx-admin::ui.dialog-footer>
            </form>
        </x-lynx-admin::ui.dialog-content>
    </x-lynx-admin::ui.dialog>
</div>
