<section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="settings-schedule">
    <h2 id="settings-schedule" class="mb-1 text-base font-semibold">Schedule</h2>
    <p class="mb-4 text-sm text-muted-foreground">Publish or unpublish this page at a set time ({{ config('app.timezone') }} time). Leave a field empty for no schedule.</p>

    @foreach ($failed as $entry)
        <x-lynx-admin::ui.alert variant="destructive" class="mb-3">
            <x-lucide-circle-alert aria-hidden="true" />
            <x-lynx-admin::ui.alert-description>Could not {{ $entry['action'] === \LynxCms\Core\Actions\Pages\SchedulePage::PUBLISH_DRAFT ? 'publish the draft' : $entry['action'] }}: {{ $entry['error'] }} ({{ $entry['language'] === \LynxCms\Core\Actions\Pages\SchedulePage::ALL_LANGUAGES ? 'every language' : $entry['language'] }}, {{ $entry['at']->format('Y-m-d H:i') }})</x-lynx-admin::ui.alert-description>
        </x-lynx-admin::ui.alert>
    @endforeach

    @unless ($canPublish)
        <p class="mb-3 text-sm text-muted-foreground">You may not change when this page is published.</p>
    @endunless

    <div class="grid gap-3">
        @if ($perLanguage)
            @foreach ($languages as $language)
                @php($code = (string) $language->code)
                @include('lynx-admin::livewire.partials.schedule-row', ['label' => $language->name, 'model' => "times.{$code}", 'key' => "schedule-{$code}"])
            @endforeach
        @else
            @include('lynx-admin::livewire.partials.schedule-row', ['label' => $hasLanguageTimes ? 'All languages' : '', 'model' => 'all', 'key' => 'schedule-all'])
        @endif
    </div>

    @if ($hasLanguageTimes)
        <label class="mt-4 flex items-center gap-2 text-sm">
            <input type="checkbox" class="blat-checkbox" wire:model.live="perLanguage" @disabled(! $canPublish)>
            Different times per language
        </label>
    @endif

    @if ($hasDraft)
        <label class="mt-4 grid max-w-xs gap-1 text-sm" data-schedule-draft>
            <span class="text-muted-foreground">Publish the draft at</span>
            <input type="datetime-local" class="blat-input" wire:model="draftAt" @disabled(! $canPublishDraft) @error('draftAt') aria-invalid="true" @enderror>
            @error('draftAt') <span role="alert" class="text-destructive text-sm">{{ $message }}</span> @enderror
        </label>
    @endif

    @if ($canPublish)
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <x-lynx-admin::ui.button variant="outline" wire:click="saveSchedule">Save schedule</x-lynx-admin::ui.button>
        </div>
    @endif
</section>
