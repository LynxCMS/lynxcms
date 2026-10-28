<div class="space-y-6">

    @foreach (['conflict', 'page'] as $key)
        @error($key) <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
    @endforeach

    {{-- Names and languages; a page without a URL has one name, which templates find it by --}}
    <section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="settings-names">
        @php($isMultilingual = lynx()->languages->isMultilingual())
        <h2 id="settings-names" class="mb-4 text-base font-semibold">{{ ! $hasUrl ? 'Name in templates' : ($isMultilingual ? 'Names and languages' : 'Name') }}</h2>
        @if (! $hasUrl && $page->parentId !== null)
            <div class="grid max-w-md gap-1.5" data-template-name>
                <label for="name-{{ $defaultCode }}" class="sr-only">Name in templates</label>
                <input id="name-{{ $defaultCode }}" type="text" class="blat-input font-mono" wire:model.live.debounce.300ms="names.{{ $defaultCode }}" @error('names.'.$defaultCode) aria-invalid="true" @enderror>
                <p class="text-muted-foreground text-sm">Templates and code find it by this name{{ $isMultilingual ? ', in every language' : '' }}. It has no address of its own: it shows wherever a template puts it. Templates that use the name must change with it.</p>
                @error('names.'.$defaultCode) <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                @if ($can['edit'])
                    <x-lynx-admin::ui.button type="button" class="mt-2 justify-self-start" wire:click="saveNames">Save name</x-lynx-admin::ui.button>
                @endif
            </div>
        @elseif ($page->parentId === null)
            <p class="text-muted-foreground text-sm">The home page has no URL name: its URL is {{ $isMultilingual ? 'the language prefix' : '/' }}.</p>
        @else
            <div class="space-y-4">
                @foreach ($languages as $language)
                    @php($code = (string) $language->code)
                    <div class="grid items-start gap-2 sm:grid-cols-[10rem_minmax(0,1fr)] sm:gap-4">
                        <label for="name-{{ $code }}" class="pt-2 text-sm font-medium">{{ $isMultilingual ? $language->name : 'URL name' }}</label>
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-3">
                                <input id="name-{{ $code }}" type="text" class="blat-input max-w-sm" wire:model.live.debounce.300ms="names.{{ $code }}"
                                       placeholder="{{ $code === $defaultCode ? '' : 'same as '.$names[$defaultCode] }}" @error('names.'.$code) aria-invalid="true" @enderror>
                                @if ($code !== $defaultCode)
                                    <label class="flex items-center gap-2 text-sm"><input class="blat-checkbox" type="checkbox" wire:model.live="active.{{ $code }}"> Published in {{ $language->name }}</label>
                                @endif
                            </div>
                            @if (($active[$code] ?? true) && $urls[$code])
                                <p class="text-muted-foreground text-sm">URL: <span class="text-foreground">{{ $urls[$code] }}</span>@if ($page->path($code) !== null && '/'.trim((string) $page->localUrl($code), '/').'/' !== $urls[$code]) <span>(the current URL will redirect)</span>@endif</p>
                            @elseif (! ($active[$code] ?? true))
                                <p class="text-muted-foreground text-sm">Not published in {{ $language->name }}: its URL returns 404.</p>
                            @endif
                            @error('names.'.$code) <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endforeach
                @if ($can['edit'])
                    <x-lynx-admin::ui.button type="button" wire:click="saveNames">Save names</x-lynx-admin::ui.button>
                @endif
            </div>
        @endif
    </section>

    <livewire:lynx-admin.page-schedule :page-id="$pageId" :key="'schedule-'.$pageId" />

    {{-- Status --}}
    <section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="settings-status">
        <h2 id="settings-status" class="mb-4 text-base font-semibold">Status</h2>
        @if ($page->hasStatus(\LynxCms\Core\Pages\PageStatus::SYSTEM))
            <p class="text-muted-foreground text-sm">A system page: the CMS or a module needs it, so its status cannot change.</p>
        @else
            <div class="space-y-2">
                @php($flags = ['unpublished' => ['Unpublished', 'Not visible on the site; pages below it are offline too'], 'hidden' => ['Hidden', 'Excluded from lists, menus and searches; still reachable by URL'], 'locked' => ['Locked', 'Not editable, movable or deletable until unlocked']])
                {{-- Hidden takes a page out of lists and menus, where a page without a URL never shows. --}}
                @php($flags = $hasUrl ? $flags : array_diff_key($flags, ['hidden' => true]))
                {{-- Unique reserves a name across the site; without a URL it does nothing else, so it only shows to be turned off. --}}
                @if ($page->parentId !== null && ($hasUrl || $page->hasStatus(\LynxCms\Core\Pages\PageStatus::UNIQUE)))
                    @php($flags['unique'] = ['Unique', 'Require the name “'.$page->name($defaultCode).'” to be unique across the site'.(lynx()->languages->isMultilingual() ? ' (default language only)' : '')])
                @endif
                {{-- Publishing needs pages.publish; the other flags pages.status (ADR-0033). --}}
                @php($flags = array_filter($flags, fn (array $flag, string $name): bool => $can[$name === 'unpublished' ? 'publish' : 'status'], ARRAY_FILTER_USE_BOTH))
                @if ($flags === [])
                    <p class="text-muted-foreground text-sm">You may not change the status of this page.</p>
                @endif
                @foreach ($flags as $flag => [$label, $help])
                    <label class="flex items-center gap-2 text-sm" wire:key="status-{{ $flag }}">
                        <input class="blat-checkbox" type="checkbox" wire:click="toggleStatus('{{ $flag }}')" @checked($page->hasStatus(\LynxCms\Core\Pages\PageStatus::NAMES[$flag]))
                               @disabled($page->hasStatus(\LynxCms\Core\Pages\PageStatus::LOCKED) && $flag !== 'locked')>
                        <span><span class="font-medium">{{ $label }}:</span> <span class="text-muted-foreground">{{ $help }}</span></span>
                    </label>
                @endforeach
            </div>
            @error('add') <p role="alert" class="text-destructive text-sm mt-2">{{ $message }}</p> @enderror
        @endif
    </section>

    {{-- Template and parent; a module's pages (forms) keep the ones it gave them --}}
    @unless ($isModulePage)
    <section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="settings-template">
        <h2 id="settings-template" class="mb-4 text-base font-semibold">Template</h2>
        @if (! $canSwitch || $templates === [])
            <p class="text-muted-foreground text-sm">Template: {{ $page->template()->name }}{{ $templates === [] ? '' : ' (cannot be changed for this page)' }}</p>
        @else
            <div class="flex flex-wrap items-center gap-3">
                <select class="blat-select max-w-xs" wire:model.live="template" aria-label="Template" @disabled($orphans !== null)>
                    <option value="{{ $page->template()->name }}">{{ $page->template()->label ?: $page->template()->name }} (current)</option>
                    @foreach ($templates as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @if ($template !== $page->template()->name && $orphans === null)
                    <x-lynx-admin::ui.button variant="outline" type="button" wire:click="previewTemplate">Switch template…</x-lynx-admin::ui.button>
                @endif
            </div>
            @if ($orphans !== null)
                <div role="alert" class="mt-4 space-y-3">
                    @if ($orphans === [])
                        <p class="text-sm">Every field of this page exists in {{ $template }}; nothing is lost.</p>
                    @else
                        <p class="text-sm">These fields are not in {{ $template }}. Their values are kept, but no longer shown (switch back to see them again): <strong>{{ implode(', ', $orphans) }}</strong>.</p>
                    @endif
                    <div class="flex gap-3">
                        <x-lynx-admin::ui.button type="button" wire:click="switchTemplate">Switch to {{ $template }}</x-lynx-admin::ui.button>
                        <x-lynx-admin::ui.button variant="outline" type="button" wire:click="cancelTemplate">Cancel</x-lynx-admin::ui.button>
                    </div>
                </div>
            @endif
            @error('template') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        @endif
    </section>

    <section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="settings-parent">
        <h2 id="settings-parent" class="mb-4 text-base font-semibold">Parent</h2>
        @if (! $canMove)
            <p class="text-muted-foreground text-sm">{{ $parentTitle ? 'Under '.$parentTitle.'. ' : '' }}This page cannot be moved.</p>
        @else
            <p class="mb-3 text-sm">Under <strong>{{ $parentTitle }}</strong>. Moving keeps the old URLs as redirects.</p>
            <div class="space-y-3">
                <livewire:lynx-admin.page-picker wire:model="parent" :multiple="false" placeholder="Search for the new parent page" :key="'parent-picker-'.$page->id.'-'.$page->revision" />
                <x-lynx-admin::ui.button variant="outline" type="button" wire:click="move">Move here</x-lynx-admin::ui.button>
                @error('parent') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
            </div>
        @endif
    </section>

    @endunless

    <livewire:lynx-admin.page-revisions :page-id="$page->id" :key="'revisions-'.$page->id" />

    <livewire:lynx-admin.page-history :page-id="$page->id" :key="'history-'.$page->id" />
</div>
