<div class="grid max-w-4xl gap-6">
    <section aria-labelledby="cache-stored" class="bg-card grid gap-3 rounded-xl border p-5">
        <h2 id="cache-stored" class="font-medium">Stored pages</h2>
        @unless ($isEnabled)
            <p role="note" class="text-sm">The page cache is off. Set <code class="font-mono">LYNX_PAGE_CACHE=true</code> to store pages; rules saved here apply then.</p>
        @endunless
        <p class="text-muted-foreground text-sm">{{ $total }} stored {{ $total === 1 ? 'page' : 'pages' }}, kept by the <span class="font-mono">{{ $driver }}</span> driver, for requests to <span class="font-mono">{{ implode(', ', $hosts) }}</span>.</p>
        @if ($page !== null)
            <div class="grid gap-2 rounded-lg border p-3 text-sm">
                <p class="font-medium">This page: {{ $pageEntries->count() }} stored {{ $pageEntries->count() === 1 ? 'address' : 'addresses' }}</p>
                <ul class="text-muted-foreground font-mono text-xs">
                    @foreach ($pageEntries as $entry)
                        <li>/{{ $entry->path }}{{ $entry->query === '' ? '' : '?'.$entry->query }}</li>
                    @endforeach
                </ul>
                <div><x-lynx-admin::ui.button type="button" variant="outline" size="sm" wire:click="clearPage">Clear this page</x-lynx-admin::ui.button></div>
            </div>
        @endif
        <div class="flex flex-wrap items-center gap-2">
            <x-lynx-admin::ui.button type="button" variant="outline" wire:click="clearAll">Clear every page</x-lynx-admin::ui.button>
            @if ($statusFor === 'stored') <span role="status" class="text-muted-foreground text-sm">{{ $status }}</span> @endif
        </div>
    </section>

    <form wire:submit="save" class="bg-card grid gap-5 rounded-xl border p-5">
        <label class="grid gap-1.5 text-sm">
            <span class="font-medium">Default lifetime (seconds)</span>
            <span class="text-muted-foreground">For templates without their own. 0 keeps pages until content changes. Empty uses {{ $configLifetime }}.</span>
            <input type="number" min="0" class="blat-input w-40" wire:model="lifetime" placeholder="{{ $configLifetime }}" @error('lifetime') aria-invalid="true" @enderror>
            @error('lifetime') <span role="alert" class="text-destructive text-sm">{{ $message }}</span> @enderror
        </label>

        <div class="grid gap-3">
            <h2 class="text-sm font-medium">Templates</h2>
            @error('templates') <span role="alert" class="text-destructive text-sm">{{ $message }}</span> @enderror
            @foreach ($templates as $name => $label)
                <fieldset class="grid gap-2 rounded-lg border p-3 text-sm" wire:key="rule-{{ $name }}">
                    <legend class="px-1 font-medium">{{ $label }} <span class="text-muted-foreground font-mono text-xs">{{ $name }}</span></legend>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label class="flex items-center gap-2"><input type="checkbox" class="blat-checkbox" wire:model="rules.{{ $name }}.cache"> Store pages of this template</label>
                        <span class="text-muted-foreground flex items-center gap-2">
                            {{ $counts[$name] ?? 0 }} stored
                            @if (($counts[$name] ?? 0) > 0)
                                <x-lynx-admin::ui.button type="button" variant="ghost" size="sm" wire:click="clearTemplate('{{ $name }}')">Clear</x-lynx-admin::ui.button>
                            @endif
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-4">
                        <label class="grid gap-1">
                            <span>Lifetime (seconds)</span>
                            <input type="number" min="0" class="blat-input w-32" wire:model="rules.{{ $name }}.lifetime" placeholder="Default">
                            @error('templates.'.$name.'.lifetime') <span role="alert" class="text-destructive">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1">
                            <span>Query parameters stored</span>
                            <input type="text" class="blat-input w-56" wire:model="rules.{{ $name }}.parameters" placeholder="page, sort">
                            @error('templates.'.$name.'.parameters.*') <span role="alert" class="text-destructive">{{ $message }}</span> @enderror
                        </label>
                    </div>
                    <label class="flex items-start gap-2">
                        <input type="checkbox" class="blat-checkbox mt-0.5" wire:model="rules.{{ $name }}.csrf">
                        <span>Store pages with forms or Livewire too <span class="text-muted-foreground block">A script swaps in a fresh security token after the page loads. Cached forms lose the honeypot's "sent too fast" check.</span></span>
                    </label>
                    <div class="flex flex-wrap items-center gap-3">
                        <span>A change clears:</span>
                        @foreach ($scopes as $scope)
                            <label class="flex items-center gap-1.5"><input type="checkbox" class="blat-checkbox" value="{{ $scope }}" wire:model="rules.{{ $name }}.clear"> {{ $scope }}</label>
                        @endforeach
                        <span class="text-muted-foreground">(none ticked: every page)</span>
                    </div>
                </fieldset>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-lynx-admin::ui.button type="submit">Save</x-lynx-admin::ui.button>
            @if ($statusFor === 'rules') <span role="status" class="text-muted-foreground text-sm">{{ $status }}</span> @endif
        </div>
    </form>

    <section aria-labelledby="cache-server" class="bg-card grid gap-3 rounded-xl border p-5">
        <h2 id="cache-server" class="font-medium">Web server rules</h2>
        <p class="text-muted-foreground text-sm">With these rules the web server sends stored pages without PHP. Without them PHP still sends them before the session starts.</p>
        <label class="flex items-center gap-2 text-sm">
            Server
            <select class="blat-select w-40" wire:model.live="server">
                @foreach ($servers as $name)
                    <option value="{{ $name }}">{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <pre class="bg-muted overflow-x-auto rounded-lg p-3 font-mono text-xs">{{ $serverText }}</pre>
    </section>
</div>
