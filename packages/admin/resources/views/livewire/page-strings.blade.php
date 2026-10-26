<form wire:submit="save" class="grid gap-4">
    @forelse ($groups as $group => $strings)
        <section class="bg-card text-card-foreground divide-y rounded-xl border shadow-sm" aria-label="{{ $group !== '' ? \Illuminate\Support\Str::headline($group) : 'Texts' }}">
            @if ($group !== '')
                <h2 class="px-5 py-3 text-sm font-semibold">{{ \Illuminate\Support\Str::headline($group) }}</h2>
            @endif
            @foreach ($strings as $position => $definition)
                @php($key = $definition->key)
                <div class="grid gap-2 p-5 sm:grid-cols-[12rem_minmax(0,1fr)] sm:gap-6" wire:key="string-{{ $key }}">
                    <div>
                        <p id="string-{{ $key }}" class="font-medium">{{ $definition->title() }}</p>
                        @if ($isSuperuser)
                            <p class="text-muted-foreground font-mono text-xs">{{ $key }}{{ $definition->format === 'markdown' ? ' · markdown' : '' }}</p>
                        @elseif ($definition->format === 'markdown')
                            <p class="text-muted-foreground text-xs">Markdown</p>
                        @endif
                        @if ($definition->placeholders() !== [])
                            <p class="text-muted-foreground text-xs">Keep {{ implode(', ', $definition->placeholders()) }}: the site fills {{ count($definition->placeholders()) === 1 ? 'it' : 'them' }} in.</p>
                        @endif
                        @if ($isSuperuser && $definition->view !== '')
                            <p class="text-muted-foreground font-mono text-xs break-all" title="Declared in this view">{{ $definition->view }}</p>
                        @endif
                    </div>
                    <x-lynx-admin::ui.tabs :value="$languages[0]['code']" class="gap-1" aria-labelledby="string-{{ $key }}">
                        @if (count($languages) > 1)
                            <x-lynx-admin::ui.tabs-list variant="compact" aria-label="Languages">
                                @foreach ($languages as $language)
                                    <x-lynx-admin::ui.tabs-trigger :value="$language['code']">{{ $language['label'] }}</x-lynx-admin::ui.tabs-trigger>
                                @endforeach
                            </x-lynx-admin::ui.tabs-list>
                        @endif
                        @foreach ($languages as $language)
                            @php($code = $language['code'])
                            <div x-show="tab === '{{ $code }}'" @if (! $loop->first) x-cloak @endif class="grid gap-1">
                                @php($placeholder = $definition->defaultIn($code))
                                @if (mb_strlen($placeholder) > 80 || $definition->format === 'markdown')
                                    <textarea rows="3" class="blat-textarea" wire:model="values.{{ $position }}.{{ $code }}" placeholder="{{ $placeholder }}" @disabled(! $canEdit) aria-label="{{ $definition->title() }} ({{ $language['label'] }})"></textarea>
                                @else
                                    <input type="text" class="blat-input" wire:model="values.{{ $position }}.{{ $code }}" placeholder="{{ $placeholder }}" @disabled(! $canEdit) aria-label="{{ $definition->title() }} ({{ $language['label'] }})">
                                @endif
                                <p class="text-muted-foreground text-xs">
                                    @if (($values[$position][$code] ?? '') === '')
                                        Shows the template's text.
                                    @elseif ($canEdit)
                                        Edited. <button type="button" class="text-primary underline-offset-4 hover:underline" wire:click="$set('values.{{ $position }}.{{ $code }}', '')">Use the template's text</button>
                                    @endif
                                </p>
                            </div>
                        @endforeach
                    </x-lynx-admin::ui.tabs>
                </div>
            @endforeach
        </section>
    @empty
        <p class="text-muted-foreground text-sm">The templates declare no {{ $scope === 'site' ? 'site texts' : 'texts for this page' }}. Add them with <code>@string('key', 'Text')</code>.</p>
    @endforelse
    @if ($groups !== [] && $canEdit)
        <div><x-lynx-admin::ui.button type="submit">Save texts</x-lynx-admin::ui.button></div>
    @endif
    @error('values') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
</form>
