<div class="space-y-6" x-data="lynxEditorGuard" x-on:keydown.window.cmd.s="save($event)" x-on:keydown.window.ctrl.s="save($event)"
     @if (app(\LynxCms\Admin\AdminAccess::class)->allows('pages.edit', $page)) wire:poll.60s="renewEditLock" @endif>
    <x-lynx-admin::ui.breadcrumb>
        <x-lynx-admin::ui.breadcrumb-list>
            <x-lynx-admin::ui.breadcrumb-item><x-lynx-admin::ui.breadcrumb-link :href="route('lynx.admin.pages')">Pages</x-lynx-admin::ui.breadcrumb-link></x-lynx-admin::ui.breadcrumb-item>
            @foreach ($ancestors as $ancestor)
                <x-lynx-admin::ui.breadcrumb-separator />
                <x-lynx-admin::ui.breadcrumb-item><x-lynx-admin::ui.breadcrumb-link :href="route('lynx.admin.pages.edit', $ancestor['id'])">{{ $ancestor['title'] }}</x-lynx-admin::ui.breadcrumb-link></x-lynx-admin::ui.breadcrumb-item>
            @endforeach
        </x-lynx-admin::ui.breadcrumb-list>
    </x-lynx-admin::ui.breadcrumb>

    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $title }}</h1>
            {{-- Whether the page is live and a draft waits, then the live page: the buttons beside are only actions. --}}
            @php
                $status = match (true) {
                    $page->hasStatus(\LynxCms\Core\Pages\PageStatus::UNPUBLISHED) => 'unpublished',
                    $page->hasStatus(\LynxCms\Core\Pages\PageStatus::HIDDEN) => 'hidden',
                    default => 'published',
                };
            @endphp
            <p class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                <span @class(['inline-flex items-center gap-1.5', 'text-success' => $status === 'published']) data-page-status="{{ $status }}">
                    <span aria-hidden="true" @class(['size-1.5 rounded-full', 'bg-current' => $status !== 'hidden', 'border border-current' => $status === 'hidden'])></span>
                    {{ ['published' => 'Published', 'unpublished' => 'Unpublished', 'hidden' => 'Hidden'][$status] }}
                </span>
                @if ($hasDraft)
                    <span class="text-warning inline-flex items-center gap-1.5" data-page-draft><span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>Draft changes</span>
                @endif
                @if ($page->hasStatus(\LynxCms\Core\Pages\PageStatus::LOCKED))
                    <span>Locked</span>
                @endif
                @if ($page->template()->hasUrl())
                    <a href="{{ $page->url() }}" target="_blank" rel="noopener" class="hover:text-foreground inline-flex items-center gap-1 underline-offset-4 hover:underline">
                        View page<x-lynx-admin::ui.icon name="external-link" class="size-3.5" aria-hidden="true" />
                    </a>
                @endif
                @if ($isSuperuser)
                    <span>Template: {{ $page->template()->name }}</span>
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            {{-- Links modules add for this page (EditorLinks), e.g. "Preview form". --}}
            @foreach (app(\LynxCms\Admin\EditorLinks::class)->for($page) as $link)
                <x-lynx-admin::ui.button variant="outline" :href="$link['url']" target="_blank" rel="noopener">{{ $link['label'] }}</x-lynx-admin::ui.button>
            @endforeach
            {{-- Saves the Content tab; Settings has its own buttons. --}}
            @if (! $isLocked && $canEdit && $isFieldsTab)
                <x-lynx-admin::ui.button variant="ghost" type="button" wire:click="review">Review changes</x-lynx-admin::ui.button>
                @if ($hasDraft)
                    <span x-data="{ confirming: false }" class="flex items-center gap-2">
                        <x-lynx-admin::ui.button variant="ghost" type="button" x-show="! confirming" x-on:click="confirming = true">Discard draft</x-lynx-admin::ui.button>
                        <span x-show="confirming" x-cloak class="flex items-center gap-2 text-sm">
                            <span class="text-destructive">Discard the draft?</span>
                            <x-lynx-admin::ui.button variant="destructive" size="sm" type="button" wire:click="discardDraft">Yes, discard</x-lynx-admin::ui.button>
                            <x-lynx-admin::ui.button variant="ghost" size="sm" type="button" x-on:click="confirming = false">Cancel</x-lynx-admin::ui.button>
                        </span>
                    </span>
                    @if ($page->template()->hasUrl() && ! auth()->check())
                        {{-- The site only shows drafts to signed in users who may edit the page; signed out it would show the live page. --}}
                        <span class="text-sm text-muted-foreground">Sign in to preview the draft.</span>
                    @elseif ($page->template()->hasUrl())
                        {{-- One link per language the page is published in: names and URLs are live, values come from the draft. --}}
                        @php($previews = lynx()->languages->all()->filter(fn ($language): bool => $page->isActiveIn($language)))
                        <span class="flex items-center gap-1">
                            @foreach ($previews as $language)
                                <x-lynx-admin::ui.button variant="outline" :size="$previews->count() > 1 ? 'sm' : 'default'" :href="$page->localUrl($language).'?preview=draft'" target="_blank" rel="noopener" :title="'Preview the draft in '.$language->name">
                                    {{ $previews->count() > 1 ? 'Preview '.$language->name : 'Preview draft' }}
                                </x-lynx-admin::ui.button>
                            @endforeach
                        </span>
                    @endif
                    <x-lynx-admin::ui.button variant="outline" type="button" wire:click="publishDraft">Publish draft</x-lynx-admin::ui.button>
                    <x-lynx-admin::ui.button type="button" wire:click="saveDraft" data-lynx-save title="Save draft (Cmd/Ctrl+S)">Save draft</x-lynx-admin::ui.button>
                @else
                    <x-lynx-admin::ui.button variant="outline" type="button" wire:click="saveDraft">Save as draft</x-lynx-admin::ui.button>
                    <x-lynx-admin::ui.button type="button" wire:click="save" data-lynx-save title="Save (Cmd/Ctrl+S)">Save</x-lynx-admin::ui.button>
                @endif
            @endif
        </div>
    </header>

    @if ($hasDraft)
        <x-lynx-admin::ui.alert tone="info" role="status">
            <x-lucide-file-pen aria-hidden="true" />
            <x-lynx-admin::ui.alert-description>You are editing a draft. Visitors see the live page until you publish the draft.</x-lynx-admin::ui.alert-description>
        </x-lynx-admin::ui.alert>
    @endif


    @if ($error)
        <p role="alert" class="text-destructive mt-1 text-sm">{{ $error }}</p>
    @endif

    @foreach (['page', 'id'] as $key)
        @error($key) <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
    @endforeach

    @error('conflict')
        {{-- Someone else saved after this form was loaded; the edits below are still in the form. --}}
        <x-lynx-admin::ui.card role="alert" class="flex flex-wrap items-center justify-between gap-4 p-4">
            <p class="text-sm text-destructive">{{ $message }}</p>
            <x-lynx-admin::ui.button variant="outline" type="button" wire:click="reload">Reload and discard my edits</x-lynx-admin::ui.button>
        </x-lynx-admin::ui.card>
    @enderror

    @if ($page->hasStatus(\LynxCms\Core\Pages\PageStatus::LOCKED))
        <x-lynx-admin::ui.card role="status" class="flex flex-wrap items-center justify-between gap-4 p-4">
            <p class="text-sm"><strong>This page is locked.</strong> It cannot be edited, moved or deleted until it is unlocked.</p>
            @if ($canUnlock) <x-lynx-admin::ui.button variant="outline" type="button" wire:click="unlock">Unlock</x-lynx-admin::ui.button> @endif
        </x-lynx-admin::ui.card>
    @endif

    @if ($editedBy)
        {{-- Someone else holds the edit lock (ADR-0037); the poll above picks it up when they leave. --}}
        <x-lynx-admin::ui.alert tone="warning" role="status">
            <x-lucide-user-pen aria-hidden="true" />
            <x-lynx-admin::ui.alert-description class="flex flex-wrap items-center gap-3">
                <span>{{ $editedBy }} is editing this page (until {{ $editedUntil }} unless they keep it open). You can view it; editing opens when they are done.</span>
                @if ($canTakeOver)
                    <x-lynx-admin::ui.button variant="outline" size="sm" wire:click="takeOverEditLock">Take over editing</x-lynx-admin::ui.button>
                @endif
            </x-lynx-admin::ui.alert-description>
        </x-lynx-admin::ui.alert>
    @elseif (! $canEdit)
        <p role="status" class="bg-card text-card-foreground rounded-xl border shadow-sm p-4 text-sm">You can view this page but not edit it.</p>
    @endif

    {{-- The server decides the section; the key re-creates the tabs when it changes. --}}
    <x-lynx-admin::ui.tabs :value="$tab" wire:key="editor-sections-{{ $tab }}">
        <x-lynx-admin::ui.tabs-list variant="underline" aria-label="Editor sections">
            {{-- Content and the template's own tabs hold fields; a dot marks one with an error. --}}
            @php($errorTabs = collect($errors->keys())->filter(fn ($key) => str_starts_with($key, 'values.'))->map(fn ($key) => $layout->tabOf(explode('.', $key)[1] ?? ''))->unique()->all())
            {{-- Then the tabs modules add for this page (EditorTabs). --}}
            @foreach (collect($layout->tabs())->mapWithKeys(fn ($t) => [$t->key => $t->label->in()])->all() + ($hasStrings ? ['texts' => 'Texts'] : []) + array_map(fn ($t) => $t['label'], $moduleTabs) + ['settings' => 'Settings'] as $value => $label)
                <x-lynx-admin::ui.tabs-trigger :value="$value" wire:click="$set('tab', '{{ $value }}')">
                    {{ $label }}
                    @if (in_array($value, $errorTabs, true))<span class="bg-destructive size-1.5 rounded-full" aria-label="has errors"></span>@endif
                </x-lynx-admin::ui.tabs-trigger>
            @endforeach
        </x-lynx-admin::ui.tabs-list>
    </x-lynx-admin::ui.tabs>

    @if ($isReviewing && $isFieldsTab)
        <x-lynx-admin::editor.review :entries="$review">
            <x-lynx-admin::ui.button type="button" wire:click="save">Save these changes</x-lynx-admin::ui.button>
            <x-lynx-admin::ui.button variant="outline" type="button" wire:click="closeReview">Keep editing</x-lynx-admin::ui.button>
        </x-lynx-admin::editor.review>
    @endif

    @if ($tab === 'settings')
        <livewire:lynx-admin.page-settings :page-id="$pageId" :key="'settings-'.$pageId" />
    @elseif ($tab === 'texts' && $hasStrings)
        <livewire:lynx-admin.page-strings :page-id="$pageId" :key="'texts-'.$pageId" />
    @elseif (isset($moduleTabs[$tab]))
        @livewire($moduleTabs[$tab]['component'], ['pageId' => $pageId], key($tab.'-'.$pageId))
    @elseif ($panel !== null)
        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="min-w-0">@include('lynx-admin::livewire.partials.editor-layout')</div>
            @include('lynx-admin::livewire.partials.editor-panel')
        </div>
    @else
        @include('lynx-admin::livewire.partials.editor-layout')
    @endif
</div>
