{{-- The page editor's header. Beside the title, a line of facts and links: whether the page is live, whether a
     draft waits (with its previews and when it goes live, if scheduled), the live page and the links modules add (EditorLinks). On the right, the
     actions: components modules add (AdminSlots), then Save, and Publish with the rest in its menu. --}}
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
    $draftGoesLive = $hasDraft ? collect(lynx()->pages->schedules($page))->firstWhere('action', \LynxCms\Core\Actions\Pages\SchedulePage::PUBLISH_DRAFT) : null;
@endphp
<header class="flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <h1 class="text-2xl font-semibold tracking-tight">{{ $title }}</h1>
        {{-- A div, not a p: the preview menu renders block elements, which a p can't hold. --}}
        <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
            <span @class(['inline-flex items-center gap-1.5', 'text-success' => $status === 'published']) data-page-status="{{ $status }}">
                <span aria-hidden="true" @class(['size-1.5 rounded-full', 'bg-current' => $status !== 'hidden', 'border border-current' => $status === 'hidden'])></span>
                {{ __('lynx-admin::pages.status.'.$status) }}
            </span>
            @if ($hasDraft)
                <span class="inline-flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="text-warning inline-flex items-center gap-1.5" data-page-draft title="{{ __('lynx-admin::editor.draft_note') }}"><span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>{{ __('lynx-admin::editor.draft_changes') }}</span>
                    @if ($draftGoesLive)
                        <span data-draft-goes-live>{{ __('lynx-admin::editor.goes_live', ['when' => $draftGoesLive['at']->isoFormat('ddd, ll LT')]) }}</span>
                    @endif
                    @if ($page->template()->hasUrl() && ! auth()->check())
                        {{-- The site only shows drafts to signed in users who may edit the page; signed out it would show the live page. --}}
                        <span data-draft-preview-signin>{{ __('lynx-admin::editor.sign_in_to_preview') }}</span>
                    @endif
                    {{-- Preview the draft: the default language on the button, the page's other published languages in its
                         menu. Names and URLs are live, values come from the draft. --}}
                    @if ($previews->isNotEmpty())
                        @php
                            $mainPreview = $previews->first(fn ($language) => $language->isDefault()) ?? $previews->first();
                            $otherPreviews = $previews->reject(fn ($language) => $language->id === $mainPreview->id);
                        @endphp
                        <span class="inline-flex" role="group" aria-label="{{ __('lynx-admin::editor.previews') }}">
                            <x-lynx-admin::ui.button size="sm" variant="outline" :href="$page->localUrl($mainPreview).'?preview=draft'" target="_blank" rel="noopener" data-draft-preview-main
                                @class(['h-7', 'rounded-e-none' => $otherPreviews->isNotEmpty()]) :title="__('lynx-admin::editor.preview_in', ['language' => $mainPreview->name])">
                                {{ $otherPreviews->isNotEmpty() ? __('lynx-admin::editor.preview_language', ['language' => $mainPreview->name]) : __('lynx-admin::editor.preview') }}<x-lynx-admin::ui.icon name="external-link" class="size-3.5" aria-hidden="true" />
                            </x-lynx-admin::ui.button>
                            @if ($otherPreviews->isNotEmpty())
                                {{-- The menu is teleported and ignored by Livewire, so the key renews it when the languages change. --}}
                                <x-lynx-admin::ui.dropdown-menu wire:key="preview-menu-{{ $otherPreviews->pluck('code')->join('-') }}">
                                    <x-lynx-admin::ui.dropdown-menu-trigger>
                                        <x-lynx-admin::ui.button size="icon" variant="outline" type="button" class="size-7 rounded-s-none border-s-0" aria-label="{{ __('lynx-admin::editor.more_previews') }}">
                                            <x-lucide-chevron-down aria-hidden="true" />
                                        </x-lynx-admin::ui.button>
                                    </x-lynx-admin::ui.dropdown-menu-trigger>
                                    <x-lynx-admin::ui.dropdown-menu-content align="start" data-draft-preview-menu>
                                        @foreach ($otherPreviews as $language)
                                            <x-lynx-admin::ui.dropdown-menu-item :href="$page->localUrl($language).'?preview=draft'" target="_blank" rel="noopener">
                                                {{ __('lynx-admin::editor.preview_in', ['language' => $language->name]) }}
                                            </x-lynx-admin::ui.dropdown-menu-item>
                                        @endforeach
                                    </x-lynx-admin::ui.dropdown-menu-content>
                                </x-lynx-admin::ui.dropdown-menu>
                            @endif
                        </span>
                    @endif
                </span>
            @endif
            @if ($page->hasStatus(\LynxCms\Core\Pages\PageStatus::LOCKED))
                <span>{{ __('lynx-admin::editor.locked') }}</span>
            @endif
            @if ($page->template()->hasUrl())
                <a href="{{ $page->url() }}" target="_blank" rel="noopener" class="{{ $link }}">{{ __('lynx-admin::editor.view_page') }}<x-lynx-admin::ui.icon name="external-link" class="size-3.5" aria-hidden="true" /></a>
            @endif
            {{-- Links modules add for this page (EditorLinks), e.g. "In cache". --}}
            @foreach (app(\LynxCms\Admin\EditorLinks::class)->for($page) as $moduleLink)
                <a data-page-link href="{{ $moduleLink['url'] }}" target="_blank" rel="noopener" class="{{ $link }}">{{ $moduleLink['label'] }}<x-lynx-admin::ui.icon name="external-link" class="size-3.5" aria-hidden="true" /></a>
            @endforeach
        </div>
    </div>

    {{-- Saves the Content tab; Settings has its own buttons. --}}
    @if (! $isLocked && $canEdit && $isFieldsTab)
        <div x-data="{ discarding: false }" class="flex items-center gap-2">
            <span x-show="discarding" x-cloak class="flex items-center gap-2 text-sm">
                <span class="text-destructive">{{ __('lynx-admin::editor.confirm_discard') }}</span>
                <x-lynx-admin::ui.button variant="destructive" size="sm" type="button" wire:click="discardDraft" x-on:click="discarding = false">{{ __('lynx-admin::editor.yes_discard') }}</x-lynx-admin::ui.button>
                <x-lynx-admin::ui.button variant="ghost" size="sm" type="button" x-on:click="discarding = false">{{ __('lynx-admin::editor.cancel') }}</x-lynx-admin::ui.button>
            </span>
            <span x-show="! discarding" class="flex items-center gap-2">
                {{-- Components modules add here (AdminSlots), e.g. a translate button. --}}
                @foreach (app(\LynxCms\Admin\AdminSlots::class)->for(\LynxCms\Admin\AdminSlots::EDITOR_HEADER, $page) as $slotComponent)
                    @livewire($slotComponent, ['pageId' => (int) $page->id], key('slot-'.$slotComponent.'-'.$page->id))
                @endforeach
                {{-- Save (save(), Cmd/Ctrl+S) saves the fields, or the draft while one waits; it never publishes. Publish
                     (PublishesPage) shows only when there is something to publish, an unpublished page or a waiting draft, and
                     its menu publishes and then leaves the editor. Otherwise, and without the publish permission, there is one
                     Save button with the same menu. --}}
                @php
                    $isUnpublished = $page->hasStatus(\LynxCms\Core\Pages\PageStatus::UNPUBLISHED);
                    $showsPublish = $canPublish && ($hasDraft || $isUnpublished);
                    $verb = $showsPublish ? 'publish' : 'save';
                    $saveHint = match (true) {
                        $hasDraft => 'save_draft_hint',
                        ! $canPublish => 'save_hint',
                        $isUnpublished => 'save_hint_unpublished',
                        default => 'save_hint_live',
                    };
                    $publishHint = $hasDraft ? 'publish_hint_draft' : 'publish_hint_unpublished';
                    $icons = ['exit' => 'x', 'view' => 'eye', 'add' => 'circle-plus', 'next' => 'arrow-right'];
                @endphp
                @if ($showsPublish)
                    <x-lynx-admin::ui.button variant="outline" type="button" wire:click="save" data-save="{{ $hasDraft ? 'draft' : 'page' }}" title="{{ __('lynx-admin::editor.'.$saveHint) }}">
                        {{ __('lynx-admin::editor.save') }}
                    </x-lynx-admin::ui.button>
                @endif
                <span class="flex" role="group" aria-label="{{ __('lynx-admin::editor.'.$verb.'_group') }}" data-lynx-save>
                    @if ($showsPublish)
                        <x-lynx-admin::ui.button type="button" class="rounded-e-none" wire:click="publish" data-publish="publish" title="{{ __('lynx-admin::editor.'.$publishHint) }}">{{ __('lynx-admin::editor.publish') }}</x-lynx-admin::ui.button>
                    @else
                        <x-lynx-admin::ui.button type="button" class="rounded-e-none" wire:click="save" data-publish="save" data-save="{{ $hasDraft ? 'draft' : 'page' }}" title="{{ __('lynx-admin::editor.'.$saveHint) }}">{{ __($hasDraft ? 'lynx-admin::editor.save_draft' : 'lynx-admin::editor.save') }}</x-lynx-admin::ui.button>
                    @endif
                    {{-- The menu is teleported and ignored by Livewire, so the key renews it when its items change. --}}
                    <x-lynx-admin::ui.dropdown-menu wire:key="publish-menu-{{ $verb }}-{{ $hasDraft ? 'draft' : 'live' }}-{{ $isUnpublished ? 'off' : 'on' }}">
                        <x-lynx-admin::ui.dropdown-menu-trigger>
                            <x-lynx-admin::ui.button type="button" size="icon" class="border-primary-foreground/20 w-8 rounded-s-none border-s" aria-label="{{ __('lynx-admin::editor.more_saves') }}">
                                <x-lucide-chevron-down aria-hidden="true" />
                            </x-lynx-admin::ui.button>
                        </x-lynx-admin::ui.dropdown-menu-trigger>
                        <x-lynx-admin::ui.dropdown-menu-content align="end" class="min-w-52" data-save-menu>
                            @foreach ($followUps as $followUp)
                                <x-lynx-admin::ui.dropdown-menu-item wire:click="publish('{{ $followUp->value }}')" data-publish-then="{{ $followUp->value }}">
                                    <x-lynx-admin::ui.icon :name="$icons[$followUp->value]" aria-hidden="true" />{{ __('lynx-admin::editor.then.'.$verb.'.'.$followUp->value) }}
                                </x-lynx-admin::ui.dropdown-menu-item>
                            @endforeach
                            <x-lynx-admin::ui.dropdown-menu-separator />
                            @if (! $hasDraft && ! $isUnpublished)
                                {{-- Save goes live on a published page, so starting a draft is a choice of its own. --}}
                                <x-lynx-admin::ui.dropdown-menu-item inset wire:click="saveDraft">{{ __('lynx-admin::editor.save_as_draft') }}</x-lynx-admin::ui.dropdown-menu-item>
                            @endif
                            <x-lynx-admin::ui.dropdown-menu-item inset wire:click="review">{{ __('lynx-admin::editor.review_changes') }}</x-lynx-admin::ui.dropdown-menu-item>
                            @if ($hasDraft)
                                <x-lynx-admin::ui.dropdown-menu-separator />
                                <x-lynx-admin::ui.dropdown-menu-item inset variant="destructive" data-discard-draft x-on:click="discarding = true">{{ __('lynx-admin::editor.discard_draft') }}</x-lynx-admin::ui.dropdown-menu-item>
                            @endif
                        </x-lynx-admin::ui.dropdown-menu-content>
                    </x-lynx-admin::ui.dropdown-menu>
                </span>
            </span>
        </div>
    @endif
</header>
