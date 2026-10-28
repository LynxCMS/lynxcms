{{-- The page cache screen's Overview, Rules and Web server tabs; `section` comes from the tabs around it (admin.index). --}}
<div class="max-w-4xl" x-show="section !== 'fragments'">
    <div x-show="section === 'overview'">@include('lynx-cache::admin.livewire.partials.overview')</div>
    <div x-show="section === 'rules'" x-cloak>@include('lynx-cache::admin.livewire.partials.rules')</div>
    <div x-show="section === 'server'" x-cloak>@include('lynx-cache::admin.livewire.partials.server')</div>
</div>
