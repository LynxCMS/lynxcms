{{-- The page editor's header. Beside the title, a line of facts and links: whether the page is live, whether a
     draft waits (with its previews), the live page and the links modules add (EditorLinks). On the right, the
     actions: one main button with the rest in its menu, plus Publish draft while a draft waits. --}}
@php
    $status = match (true) {
        $page->hasStatus(\LynxCms\Core\Pages\PageStatus::UNPUBLISHED) => 'unpublished',
        $page->hasStatus(\LynxCms\Core\Pages\PageStatus::HIDDEN) => 'hidden',
        default => 'published',
    };
    $link = 'hover:text-foreground inline-flex items-center gap-1 underline-offset-4 hover:underline';
    $previews = $hasDraft && $page->template()->hasUrl() && auth()->check()
        ? lynx()->languages->all()->filter(fn ($language): bool => $page->isActiveIn($language))
        : collect();
@endphp
<header class="flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <h1 class="text-2xl font-semibold tracking-tight">{{ $title }}</h1>
        <p class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
            <span @class(['inline-flex items-center gap-1.5', 'text-success' => $status === 'published']) data-page-status="{{ $status }}">
                <span aria-hidden="true" @class(['size-1.5 rounded-full', 'bg-current' => $status !== 'hidden', 'border border-current' => $status === 'hidden'])></span>
                {{ ['published' => 'Published', 'unpublished' => 'Unpublished', 'hidden' => 'Hidden'][$status] }}
            </span>
            @if ($hasDraft)
                <span class="inline-flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="text-warning inline-flex items-center gap-1.5" data-page-draft title="Visitors see the live page until you publish the draft."><span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>Draft changes</span>
                    @if ($page->template()->hasUrl() && ! auth()->check())
                        {{-- The site only shows drafts to signed in users who may edit the page; signed out it would show the live page. --}}
                        <span data-draft-preview-signin>(sign in to preview)</span>
                    @endif
                    {{-- One link per language the page is published in: names and URLs are live, values come from the draft. --}}
                    @foreach ($previews as $language)
                        <a href="{{ $page->localUrl($language) }}?preview=draft" target="_blank" rel="noopener" class="{{ $link }}" title="Preview the draft in {{ $language->name }}">
                            {{ $previews->count() > 1 ? 'Preview '.$language->tabLabel() : 'Preview' }}<x-lynx-admin::ui.icon name="external-link" class="size-3.5" aria-hidden="true" />
                        </a>
                    @endforeach
                </span>
            @endif
            @if ($page->hasStatus(\LynxCms\Core\Pages\PageStatus::LOCKED))
                <span>Locked</span>
            @endif
            @if ($page->template()->hasUrl())
                <a href="{{ $page->url() }}" target="_blank" rel="noopener" class="{{ $link }}">View page<x-lynx-admin::ui.icon name="external-link" class="size-3.5" aria-hidden="true" /></a>
            @endif
            {{-- Links modules add for this page (EditorLinks), e.g. "SEO preview". --}}
            @foreach (app(\LynxCms\Admin\EditorLinks::class)->for($page) as $moduleLink)
                <a data-page-link href="{{ $moduleLink['url'] }}" target="_blank" rel="noopener" class="{{ $link }}">{{ $moduleLink['label'] }}<x-lynx-admin::ui.icon name="external-link" class="size-3.5" aria-hidden="true" /></a>
            @endforeach
            @if ($isSuperuser)
                <span>Template: {{ $page->template()->name }}</span>
            @endif
        </p>
    </div>

    {{-- Saves the Content tab; Settings has its own buttons. --}}
    @if (! $isLocked && $canEdit && $isFieldsTab)
        <div x-data="{ discarding: false }" class="flex items-center gap-2">
            <span x-show="discarding" x-cloak class="flex items-center gap-2 text-sm">
                <span class="text-destructive">Discard the draft?</span>
                <x-lynx-admin::ui.button variant="destructive" size="sm" type="button" wire:click="discardDraft" x-on:click="discarding = false">Yes, discard</x-lynx-admin::ui.button>
                <x-lynx-admin::ui.button variant="ghost" size="sm" type="button" x-on:click="discarding = false">Cancel</x-lynx-admin::ui.button>
            </span>
            <span x-show="! discarding" class="flex items-center gap-2">
                @if ($hasDraft)
                    <x-lynx-admin::ui.button variant="outline" type="button" wire:click="publishDraft">Publish draft</x-lynx-admin::ui.button>
                @endif
                {{-- A split button: the main save, then a menu with the other ways to save. --}}
                <span class="flex" role="group" aria-label="Save">
                    @if ($hasDraft)
                        <x-lynx-admin::ui.button type="button" class="rounded-e-none" wire:click="saveDraft" data-lynx-save title="Save draft (Cmd/Ctrl+S)">Save draft</x-lynx-admin::ui.button>
                    @else
                        <x-lynx-admin::ui.button type="button" class="rounded-e-none" wire:click="save" data-lynx-save title="Save (Cmd/Ctrl+S)">Save</x-lynx-admin::ui.button>
                    @endif
                    {{-- The menu is teleported and ignored by Livewire, so the key renews it when its items change. --}}
                    <x-lynx-admin::ui.dropdown-menu wire:key="save-menu-{{ $hasDraft ? 'draft' : 'live' }}">
                        <x-lynx-admin::ui.dropdown-menu-trigger>
                            <x-lynx-admin::ui.button type="button" size="icon" class="border-primary-foreground/20 w-8 rounded-s-none border-s" aria-label="More ways to save">
                                <x-lucide-chevron-down aria-hidden="true" />
                            </x-lynx-admin::ui.button>
                        </x-lynx-admin::ui.dropdown-menu-trigger>
                        <x-lynx-admin::ui.dropdown-menu-content align="end" class="min-w-44" data-save-menu>
                            @unless ($hasDraft)
                                <x-lynx-admin::ui.dropdown-menu-item wire:click="saveDraft">Save as draft</x-lynx-admin::ui.dropdown-menu-item>
                            @endunless
                            <x-lynx-admin::ui.dropdown-menu-item wire:click="review">Review changes</x-lynx-admin::ui.dropdown-menu-item>
                            @if ($hasDraft)
                                <x-lynx-admin::ui.dropdown-menu-separator />
                                <x-lynx-admin::ui.dropdown-menu-item variant="destructive" data-discard-draft x-on:click="discarding = true">Discard draft…</x-lynx-admin::ui.dropdown-menu-item>
                            @endif
                        </x-lynx-admin::ui.dropdown-menu-content>
                    </x-lynx-admin::ui.dropdown-menu>
                </span>
            </span>
        </div>
    @endif
</header>
