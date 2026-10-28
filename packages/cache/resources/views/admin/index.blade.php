{{--
    Settings > Page cache, in tabs: Overview (what is stored, and clearing), Rules (per template),
    Fragments and Web server. Tabs switch in the browser and keep their place in the address's
    hash, so a link can open one; Save shows with the Rules tab and submits its form.
--}}
<x-lynx-admin::layout title="Page cache">
    @php($tabs = ['overview' => 'Overview', 'rules' => 'Rules', 'fragments' => 'Fragments', 'server' => 'Web server'])
    <div class="grid gap-6" x-data="{ tabs: {{ Js::from(array_keys($tabs)) }}, section: 'overview', fromHash() { const tab = location.hash.slice(1); this.section = this.tabs.includes(tab) ? tab : 'overview' } }"
         x-init="fromHash(); $watch('section', (tab) => history.replaceState(null, '', '#' + tab))" x-on:hashchange.window="fromHash()">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="grid gap-1.5">
                <h1 class="text-2xl font-semibold tracking-tight">Page cache</h1>
                <p class="text-muted-foreground max-w-2xl text-sm">Pages guests see, stored as HTML and sent again until their content changes.</p>
            </div>
            <x-lynx-admin::ui.button type="submit" form="cache-rules" x-show="section === 'rules'" x-cloak>Save</x-lynx-admin::ui.button>
        </header>

        <nav class="flex gap-5 overflow-x-auto border-b" aria-label="Page cache sections">
            @foreach ($tabs as $tab => $label)
                <button type="button" data-cache-tab="{{ $tab }}" x-on:click="section = '{{ $tab }}'"
                        class="text-muted-foreground -mb-px shrink-0 border-b-2 border-transparent pb-2.5 text-sm font-medium whitespace-nowrap hover:text-foreground"
                        x-bind:class="section === '{{ $tab }}' && 'border-primary! text-foreground'" x-bind:aria-current="section === '{{ $tab }}' ? 'page' : 'false'">{{ $label }}</button>
            @endforeach
        </nav>

        <livewire:lynx-cache.page-cache />
        <div x-show="section === 'fragments'" x-cloak><livewire:lynx-cache.fragments /></div>
    </div>
</x-lynx-admin::layout>
