{{-- The Find pages screen (FinderScreen): the groups of conditions, the selector they make and the pages found. --}}
@php($groups = $search['groups'])
<div class="grid grid-cols-1 gap-8" data-finder>
    <section class="grid grid-cols-1 gap-4" aria-label="{{ __('lynx-finder::admin.title') }}">
        @if (count($groups) > 1)
            <div class="flex flex-wrap items-center gap-3">
                <label for="finder-match" class="blat-label">{{ __('lynx-finder::admin.match.label') }}</label>
                <select id="finder-match" class="blat-select w-auto" wire:model.live="search.match" data-finder-match>
                    <option value="all">{{ __('lynx-finder::admin.match.all') }}</option>
                    <option value="any">{{ __('lynx-finder::admin.match.any') }}</option>
                </select>
            </div>
        @endif

        @foreach ($groups as $g => $group)
            @include('lynx-finder::admin.partials.group', ['g' => $g, 'group' => $group, 'removable' => count($groups) > 1])
        @endforeach

        <div class="flex flex-wrap items-center gap-3">
            <x-lynx-admin::ui.button variant="outline" type="button" wire:click="addGroup" data-finder-add-group><x-lucide-plus aria-hidden="true" /> {{ __('lynx-finder::admin.group.add') }}</x-lynx-admin::ui.button>
            <button type="button" wire:click="resetSearch" class="text-muted-foreground text-sm font-medium underline-offset-4 hover:underline" data-finder-reset>{{ __('lynx-finder::admin.reset') }}</button>
        </div>
    </section>

    @include('lynx-finder::admin.partials.selector')
    @include('lynx-finder::admin.partials.results')
</div>
