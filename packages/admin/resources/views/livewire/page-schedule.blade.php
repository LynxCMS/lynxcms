<section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="settings-schedule">
    <h2 id="settings-schedule" class="mb-1 text-base font-semibold">{{ __('lynx-admin::settings.schedule.title') }}</h2>
    <p class="mb-4 text-sm text-muted-foreground">{{ __('lynx-admin::settings.schedule.intro', ['timezone' => config('app.timezone')]) }}</p>

    @foreach ($failed as $entry)
        <x-lynx-admin::ui.alert variant="destructive" class="mb-3">
            <x-lucide-circle-alert aria-hidden="true" />
            <x-lynx-admin::ui.alert-description>{{ __('lynx-admin::settings.schedule.failed', ['action' => __('lynx-admin::settings.schedule.action_'.$entry['action']), 'error' => $entry['error'], 'language' => $entry['language'] === \LynxCms\Core\Actions\Pages\SchedulePage::ALL_LANGUAGES ? __('lynx-admin::settings.schedule.every_language') : $entry['language'], 'at' => $entry['at']->format('Y-m-d H:i')]) }}</x-lynx-admin::ui.alert-description>
        </x-lynx-admin::ui.alert>
    @endforeach

    @unless ($canPublish)
        <p class="mb-3 text-sm text-muted-foreground">{{ __('lynx-admin::settings.schedule.denied') }}</p>
    @endunless

    <div class="grid gap-3">
        @if ($perLanguage)
            @foreach ($languages as $language)
                @php($code = (string) $language->code)
                @include('lynx-admin::livewire.partials.schedule-row', ['label' => $language->name, 'model' => "times.{$code}", 'key' => "schedule-{$code}"])
            @endforeach
        @else
            @include('lynx-admin::livewire.partials.schedule-row', ['label' => $hasLanguageTimes ? __('lynx-admin::settings.schedule.all_languages') : '', 'model' => 'all', 'key' => 'schedule-all'])
        @endif
    </div>

    @if ($hasLanguageTimes)
        <label class="mt-4 flex items-center gap-2 text-sm">
            <input type="checkbox" class="blat-checkbox" wire:model.live="perLanguage" @disabled(! $canPublish)>
            {{ __('lynx-admin::settings.schedule.per_language') }}
        </label>
    @endif

    @if ($hasDraft)
        <label class="mt-4 grid max-w-xs gap-1 text-sm" data-schedule-draft>
            <span class="text-muted-foreground">{{ __('lynx-admin::settings.schedule.publish_draft_at') }}</span>
            <input type="datetime-local" class="blat-input" wire:model="draftAt" @disabled(! $canPublishDraft) @error('draftAt') aria-invalid="true" @enderror>
            @error('draftAt') <span role="alert" class="text-destructive text-sm">{{ $message }}</span> @enderror
        </label>
    @endif

    @if ($canPublish)
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <x-lynx-admin::ui.button variant="outline" wire:click="saveSchedule">{{ __('lynx-admin::settings.schedule.save') }}</x-lynx-admin::ui.button>
        </div>
    @endif
</section>
