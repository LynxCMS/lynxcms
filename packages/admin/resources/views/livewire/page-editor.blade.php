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

    @include('lynx-admin::livewire.partials.editor-header')

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
