<div class="space-y-6">

    @foreach (['conflict', 'page'] as $key)
        @error($key) <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
    @endforeach

    {{-- Names and languages; a page without a URL has one name, which templates find it by --}}
    <section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="settings-names">
        @php($isMultilingual = lynx()->languages->isMultilingual())
        <h2 id="settings-names" class="mb-4 text-base font-semibold">{{ __(! $hasUrl ? 'lynx-admin::settings.name_in_templates' : ($isMultilingual ? 'lynx-admin::settings.names_languages' : 'lynx-admin::settings.name')) }}</h2>
        @if (! $hasUrl && $page->parentId !== null)
            <div class="grid max-w-md gap-1.5" data-template-name>
                <label for="name-{{ $defaultCode }}" class="sr-only">{{ __('lynx-admin::settings.name_in_templates') }}</label>
                <input id="name-{{ $defaultCode }}" type="text" class="blat-input font-mono" wire:model.live.debounce.300ms="names.{{ $defaultCode }}" @error('names.'.$defaultCode) aria-invalid="true" @enderror>
                <p class="text-muted-foreground text-sm">{{ __($isMultilingual ? 'lynx-admin::settings.name_help_languages' : 'lynx-admin::settings.name_help') }}</p>
                @error('names.'.$defaultCode) <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
                @if ($can['edit'])
                    <x-lynx-admin::ui.button type="button" class="mt-2 justify-self-start" wire:click="saveNames">{{ __('lynx-admin::settings.save_name') }}</x-lynx-admin::ui.button>
                @endif
            </div>
        @elseif ($page->parentId === null)
            <p class="text-muted-foreground text-sm">{{ __($isMultilingual ? 'lynx-admin::settings.home_no_name_languages' : 'lynx-admin::settings.home_no_name') }}</p>
        @else
            <div class="space-y-4">
                @foreach ($languages as $language)
                    @php($code = (string) $language->code)
                    <div class="grid items-start gap-2 sm:grid-cols-[10rem_minmax(0,1fr)] sm:gap-4">
                        <label for="name-{{ $code }}" class="pt-2 text-sm font-medium">{{ $isMultilingual ? $language->name : __('lynx-admin::settings.url_name') }}</label>
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-3">
                                <input id="name-{{ $code }}" type="text" class="blat-input max-w-sm" wire:model.live.debounce.300ms="names.{{ $code }}"
                                       placeholder="{{ $code === $defaultCode ? '' : __('lynx-admin::settings.same_as', ['name' => $names[$defaultCode]]) }}" @error('names.'.$code) aria-invalid="true" @enderror>
                                @if ($code !== $defaultCode)
                                    <label class="flex items-center gap-2 text-sm"><input class="blat-checkbox" type="checkbox" wire:model.live="active.{{ $code }}"> {{ __('lynx-admin::settings.published_in', ['language' => $language->name]) }}</label>
                                @endif
                            </div>
                            @if (($active[$code] ?? true) && $urls[$code])
                                <p class="text-muted-foreground text-sm">{{ __('lynx-admin::settings.url') }} <span class="text-foreground">{{ $urls[$code] }}</span>@if ($page->path($code) !== null && $page->localUrl($code) !== $urls[$code]) <span data-url-will-redirect>{{ __('lynx-admin::settings.will_redirect') }}</span>@endif</p>
                            @elseif (! ($active[$code] ?? true))
                                <p class="text-muted-foreground text-sm">{{ __('lynx-admin::settings.not_published_in', ['language' => $language->name]) }}</p>
                            @endif
                            @error('names.'.$code) <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endforeach
                @if ($can['edit'])
                    <x-lynx-admin::ui.button type="button" wire:click="saveNames">{{ __('lynx-admin::settings.save_names') }}</x-lynx-admin::ui.button>
                @endif
            </div>
        @endif
    </section>

    <livewire:lynx-admin.page-schedule :page-id="$pageId" :key="'schedule-'.$pageId" />

    {{-- Status --}}
    <section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="settings-status">
        <h2 id="settings-status" class="mb-4 text-base font-semibold">{{ __('lynx-admin::settings.status.title') }}</h2>
        @if ($page->hasStatus(\LynxCms\Core\Pages\PageStatus::SYSTEM))
            <p class="text-muted-foreground text-sm">{{ __('lynx-admin::settings.status.system') }}</p>
        @else
            <div class="space-y-2">
                @php($flags = ['unpublished' => [__('lynx-admin::settings.status.unpublished'), __('lynx-admin::settings.status.unpublished_help')], 'hidden' => [__('lynx-admin::settings.status.hidden'), __('lynx-admin::settings.status.hidden_help')], 'locked' => [__('lynx-admin::settings.status.locked'), __('lynx-admin::settings.status.locked_help')]])
                {{-- Hidden takes a page out of lists and menus, where a page without a URL never shows. --}}
                @php($flags = $hasUrl ? $flags : array_diff_key($flags, ['hidden' => true]))
                {{-- Unique reserves a name across the site; without a URL it does nothing else, so it only shows to be turned off. --}}
                @if ($page->parentId !== null && ($hasUrl || $page->hasStatus(\LynxCms\Core\Pages\PageStatus::UNIQUE)))
                    @php($flags['unique'] = [__('lynx-admin::settings.status.unique'), __(lynx()->languages->isMultilingual() ? 'lynx-admin::settings.status.unique_help_languages' : 'lynx-admin::settings.status.unique_help', ['name' => $page->name($defaultCode)])])
                @endif
                {{-- Publishing needs pages.publish; the other flags pages.status (ADR-0033). --}}
                @php($flags = array_filter($flags, fn (array $flag, string $name): bool => $can[$name === 'unpublished' ? 'publish' : 'status'], ARRAY_FILTER_USE_BOTH))
                @if ($flags === [])
                    <p class="text-muted-foreground text-sm">{{ __('lynx-admin::settings.status.denied') }}</p>
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
        <h2 id="settings-template" class="mb-4 text-base font-semibold">{{ __('lynx-admin::settings.template.title') }}</h2>
        @if (! $canSwitch || $templates === [])
            <p class="text-muted-foreground text-sm">{{ __($templates === [] ? 'lynx-admin::settings.template.current' : 'lynx-admin::settings.template.fixed', ['name' => $page->template()->name]) }}</p>
        @else
            <div class="flex flex-wrap items-center gap-3">
                <select class="blat-select max-w-xs" wire:model.live="template" aria-label="{{ __('lynx-admin::settings.template.title') }}" @disabled($orphans !== null)>
                    <option value="{{ $page->template()->name }}">{{ __('lynx-admin::settings.template.current_option', ['name' => $page->template()->label ?: $page->template()->name]) }}</option>
                    @foreach ($templates as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @if ($template !== $page->template()->name && $orphans === null)
                    <x-lynx-admin::ui.button variant="outline" type="button" wire:click="previewTemplate">{{ __('lynx-admin::settings.template.switch') }}</x-lynx-admin::ui.button>
                @endif
            </div>
            @if ($orphans !== null)
                <div role="alert" class="mt-4 space-y-3">
                    @if ($orphans === [])
                        <p class="text-sm">{{ __('lynx-admin::settings.template.nothing_lost', ['template' => $template]) }}</p>
                    @else
                        <p class="text-sm">{{ __('lynx-admin::settings.template.orphans', ['template' => $template]) }} <strong>{{ implode(', ', $orphans) }}</strong>.</p>
                    @endif
                    <div class="flex gap-3">
                        <x-lynx-admin::ui.button type="button" wire:click="switchTemplate">{{ __('lynx-admin::settings.template.switch_to', ['template' => $template]) }}</x-lynx-admin::ui.button>
                        <x-lynx-admin::ui.button variant="outline" type="button" wire:click="cancelTemplate">{{ __('lynx-admin::settings.template.cancel') }}</x-lynx-admin::ui.button>
                    </div>
                </div>
            @endif
            @error('template') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        @endif
    </section>

    <section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="settings-parent">
        <h2 id="settings-parent" class="mb-4 text-base font-semibold">{{ __('lynx-admin::settings.parent.title') }}</h2>
        @if (! $canMove)
            <p class="text-muted-foreground text-sm">{{ $parentTitle ? __('lynx-admin::settings.parent.under', ['page' => $parentTitle]).' ' : '' }}{{ __('lynx-admin::settings.parent.cannot_move') }}</p>
        @else
            <p class="mb-3 text-sm">{!! __('lynx-admin::settings.parent.under', ['page' => '<strong>'.e($parentTitle).'</strong>']) !!} {{ __('lynx-admin::settings.parent.keeps_urls') }}</p>
            <div class="space-y-3">
                <livewire:lynx-admin.page-picker wire:model="parent" :multiple="false" :placeholder="__('lynx-admin::settings.parent.search')" :key="'parent-picker-'.$page->id.'-'.$page->revision" />
                <x-lynx-admin::ui.button variant="outline" type="button" wire:click="move">{{ __('lynx-admin::settings.parent.move') }}</x-lynx-admin::ui.button>
                @error('parent') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
            </div>
        @endif
    </section>

    @endunless

    <livewire:lynx-admin.page-revisions :page-id="$page->id" :key="'revisions-'.$page->id" />

    <livewire:lynx-admin.page-history :page-id="$page->id" :key="'history-'.$page->id" />
</div>
