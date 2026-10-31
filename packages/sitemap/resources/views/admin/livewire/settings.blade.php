<div class="grid max-w-3xl gap-6">
    <section aria-labelledby="sitemap-files" class="bg-card grid gap-2 rounded-xl border p-5">
        <h2 id="sitemap-files" class="font-medium">{{ __('lynx-sitemap::admin.files') }}</h2>
        <p class="text-muted-foreground text-sm">{{ trans_choice('lynx-sitemap::admin.pages', $pages) }} {{ count($parts) > 1 ? __('lynx-sitemap::admin.in_parts', ['count' => count($parts)]) : __('lynx-sitemap::admin.in_file') }}. {{ __('lynx-sitemap::admin.submit') }} <a href="{{ url('/sitemap.xml') }}" class="text-primary underline" target="_blank">{{ url('/sitemap.xml') }}</a> {{ __('lynx-sitemap::admin.to_engines') }}</p>
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
            <span class="font-medium">{{ __('lynx-sitemap::admin.per_file') }}</span>
            <span class="text-muted-foreground">{{ __('lynx-sitemap::admin.per_file_help', ['default' => number_format($configPerFile), 'max' => number_format($max)]) }}</span>
            <input type="number" min="1" max="{{ $max }}" class="blat-input w-40" wire:model="perFile" placeholder="{{ $configPerFile }}" @error('perFile') aria-invalid="true" @enderror>
            @error('perFile') <span role="alert" class="text-destructive text-sm">{{ $message }}</span> @enderror
        </label>

        <fieldset class="grid gap-1.5 text-sm">
            <legend class="mb-1 font-medium">{{ __('lynx-sitemap::admin.group_by') }}</legend>
            <label class="flex items-center gap-2"><input type="radio" class="blat-radio" value="" wire:model="groupBy"> {{ __('lynx-sitemap::admin.as_config') }}</label>
            <label class="flex items-center gap-2"><input type="radio" class="blat-radio" value="none" wire:model="groupBy"> {{ __('lynx-sitemap::admin.one_list') }}</label>
            <label class="flex items-center gap-2"><input type="radio" class="blat-radio" value="template" wire:model="groupBy"> {{ __('lynx-sitemap::admin.per_template') }}</label>
        </fieldset>

        <fieldset class="grid gap-1.5 text-sm">
            <legend class="mb-1 font-medium">{{ __('lynx-sitemap::admin.left_out') }}</legend>
            @foreach ($templates as $name => $label)
                <label class="flex items-center gap-2"><input type="checkbox" class="blat-checkbox" value="{{ $name }}" wire:model="exclude"> {{ $label }} <span class="text-muted-foreground font-mono text-xs">{{ $name }}</span></label>
            @endforeach
            @error('exclude.*') <span role="alert" class="text-destructive text-sm">{{ $message }}</span> @enderror
        </fieldset>

        <div class="flex flex-wrap items-center gap-2">
            <x-lynx-admin::ui.button type="submit">{{ __('lynx-sitemap::admin.save') }}</x-lynx-admin::ui.button>
            <x-lynx-admin::ui.button type="button" variant="outline" wire:click="clearCache">{{ __('lynx-sitemap::admin.clear') }}</x-lynx-admin::ui.button>
            @if ($status !== '') <span role="status" class="text-muted-foreground text-sm">{{ $status }}</span> @endif
        </div>
    </form>
</div>
