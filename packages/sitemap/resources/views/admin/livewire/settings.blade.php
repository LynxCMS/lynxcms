<div class="grid max-w-3xl gap-6">
    <section aria-labelledby="sitemap-files" class="bg-card grid gap-2 rounded-xl border p-5">
        <h2 id="sitemap-files" class="font-medium">Files</h2>
        <p class="text-muted-foreground text-sm">{{ $pages }} {{ $pages === 1 ? 'page' : 'pages' }} in {{ max(1, count($parts)) }} {{ count($parts) > 1 ? 'parts' : 'file' }}. Submit <a href="{{ url('/sitemap.xml') }}" class="text-primary underline" target="_blank">{{ url('/sitemap.xml') }}</a> to search engines.</p>
        @if (count($parts) > 1)
            <ul class="grid gap-1 font-mono text-xs">
                @foreach ($parts as $part)
                    <li><a href="{{ url('/'.$part) }}" class="hover:underline" target="_blank">{{ $part }}</a></li>
                @endforeach
            </ul>
        @endif
    </section>

    <form wire:submit="save" class="bg-card grid gap-5 rounded-xl border p-5">
        <label class="grid gap-1.5 text-sm">
            <span class="font-medium">URLs per file</span>
            <span class="text-muted-foreground">Past this many the sitemap splits into parts under an index. Empty uses {{ number_format($configPerFile) }}; at most {{ number_format($max) }}.</span>
            <input type="number" min="1" max="{{ $max }}" class="blat-input w-40" wire:model="perFile" placeholder="{{ $configPerFile }}" @error('perFile') aria-invalid="true" @enderror>
            @error('perFile') <span role="alert" class="text-destructive text-sm">{{ $message }}</span> @enderror
        </label>

        <fieldset class="grid gap-1.5 text-sm">
            <legend class="mb-1 font-medium">Group by</legend>
            <label class="flex items-center gap-2"><input type="radio" class="blat-radio" value="" wire:model="groupBy"> As in the config</label>
            <label class="flex items-center gap-2"><input type="radio" class="blat-radio" value="none" wire:model="groupBy"> One list</label>
            <label class="flex items-center gap-2"><input type="radio" class="blat-radio" value="template" wire:model="groupBy"> One sitemap per template, under an index</label>
        </fieldset>

        <fieldset class="grid gap-1.5 text-sm">
            <legend class="mb-1 font-medium">Templates left out</legend>
            @foreach ($templates as $name => $label)
                <label class="flex items-center gap-2"><input type="checkbox" class="blat-checkbox" value="{{ $name }}" wire:model="exclude"> {{ $label }} <span class="text-muted-foreground font-mono text-xs">{{ $name }}</span></label>
            @endforeach
            @error('exclude.*') <span role="alert" class="text-destructive text-sm">{{ $message }}</span> @enderror
        </fieldset>

        <div class="flex flex-wrap items-center gap-2">
            <x-lynx-admin::ui.button type="submit">Save</x-lynx-admin::ui.button>
            <x-lynx-admin::ui.button type="button" variant="outline" wire:click="clearCache">Clear cache</x-lynx-admin::ui.button>
            @if ($status !== '') <span role="status" class="text-muted-foreground text-sm">{{ $status }}</span> @endif
        </div>
    </form>
</div>
