{{-- Chooses templates for a Livewire array property (a template's family lists, ADR-0032): the chosen ones as rows
     with a Remove button, and a search box listing the others by label and name. It runs in the browser, since the
     list of templates is short, and syncs with the property on the next request (the Save button). Results float
     over the page from <body>, anchored to the box; the arrow keys reach them and Escape returns to the box. --}}
@props(['model', 'options', 'legend', 'hint'])
<fieldset class="grid gap-2" data-template-chooser="{{ $model }}"
    x-data="{
        chosen: $wire.entangle(@js($model)),
        options: @js($options),
        query: '',
        open: false,
        get matches() {
            const query = this.query.trim().toLowerCase();

            return this.options.filter((option) => ! this.chosen.includes(option.name)
                && (query === '' || option.name.includes(query) || option.label.toLowerCase().includes(query)));
        },
        labelOf(name) {
            return this.options.find((option) => option.name === name)?.label ?? name;
        },
        add(name) {
            this.chosen = [...this.chosen, name];
            this.query = '';
            this.$refs.search.focus();
        },
        remove(name) {
            this.chosen = this.chosen.filter((chosen) => chosen !== name);
        },
    }" x-on:keydown.escape.window="open = false">
    <legend class="mb-1 text-sm font-medium">{{ $legend }}</legend>

    <ul class="divide-y overflow-hidden rounded-md border" x-show="chosen.length > 0" x-cloak aria-label="{{ __('lynx-admin::templates.family.chosen', ['list' => $legend]) }}">
        <template x-for="name in chosen" x-bind:key="name">
            <li class="bg-card flex items-center gap-2 px-2 py-1.5 text-sm">
                <span class="min-w-0 flex-1">
                    <span class="font-medium" x-text="labelOf(name)"></span>
                    <span class="text-muted-foreground ms-2 font-mono text-xs" x-text="name"></span>
                </span>
                <button type="button" x-on:click="remove(name)" class="text-muted-foreground hover:text-destructive flex size-6 items-center justify-center rounded-sm"
                    x-bind:aria-label="@js(__('lynx-admin::templates.family.remove')).replace(':template', labelOf(name))"><x-lucide-x class="size-3.5" aria-hidden="true" /></button>
            </li>
        </template>
    </ul>

    <input type="search" class="blat-input" x-ref="search" x-model="query" x-on:focus="open = true" x-on:input="open = true"
        x-on:keydown.down.prevent="open = true; $nextTick(() => $refs.results?.querySelector('button')?.focus())"
        x-on:keydown.enter.prevent="matches.length > 0 && add(matches[0].name)"
        placeholder="{{ __('lynx-admin::templates.family.search') }}" autocomplete="off" aria-label="{{ __('lynx-admin::templates.family.search_in', ['list' => $legend]) }}">
    <template x-teleport="body">
        <ul x-ref="results" x-show="open && matches.length > 0" x-cloak x-blat-anchor.bottom-start.offset.4.match-width="$refs.search"
            x-on:keydown.down.prevent="$focus.within($refs.results).wrap().next()" x-on:keydown.up.prevent="$focus.within($refs.results).wrap().previous()" x-on:keydown.escape.stop="$refs.search.focus()"
            x-on:click.outside="if (! $refs.search.contains($event.target)) open = false"
            class="bg-popover text-popover-foreground divide-border fixed z-50 max-h-72 w-80 max-w-[calc(100vw-2rem)] divide-y overflow-y-auto rounded-md border shadow-md" aria-label="{{ __('lynx-admin::templates.family.matching') }}">
            <template x-for="option in matches" x-bind:key="option.name">
                <li>
                    <button type="button" x-on:click="add(option.name)" class="flex w-full items-center gap-3 px-3 py-2 text-start text-sm hover:bg-accent hover:text-accent-foreground focus:bg-accent">
                        <span class="min-w-0 flex-1" x-text="option.label"></span>
                        <span class="text-muted-foreground shrink-0 font-mono text-xs" x-text="option.name"></span>
                    </button>
                </li>
            </template>
        </ul>
    </template>
    <p class="text-muted-foreground text-sm">{{ $hint }}</p>
</fieldset>
