<div class="space-y-6">
    <x-lynx-admin::ui.breadcrumb>
        <x-lynx-admin::ui.breadcrumb-list>
            <x-lynx-admin::ui.breadcrumb-item><x-lynx-admin::ui.breadcrumb-link :href="route('lynx.admin.forms')">{{ __('lynx-forms::admin.title') }}</x-lynx-admin::ui.breadcrumb-link></x-lynx-admin::ui.breadcrumb-item>
        </x-lynx-admin::ui.breadcrumb-list>
    </x-lynx-admin::ui.breadcrumb>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $form?->text('label')->in() ?? $submission->form }}</h1>
            <p class="text-muted-foreground text-sm">
                {{ __('lynx-forms::admin.submission.received', ['when' => $submission->created_at->isoFormat('ddd, ll LT')]) }}
                @if ($submission->language() && lynx()->languages->isMultilingual()) · {{ __('lynx-forms::admin.submission.in_language', ['language' => $submission->language()->name]) }} @endif
                @if ($page) · {{ __('lynx-forms::admin.submission.from') }} <a href="{{ $page->url }}" class="underline" target="_blank" rel="noopener">{{ $page->title }}</a> @endif
                @if ($account = $submission->account()) · {{ __('lynx-forms::admin.submission.by', ['account' => $account]) }} @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-lynx-admin::ui.badge :variant="$submission->status === 'new' ? 'default' : 'secondary'">{{ __('lynx-forms::admin.status.'.$submission->status) }}</x-lynx-admin::ui.badge>
            @foreach (['read' => __('lynx-forms::admin.submission.mark_read'), 'archived' => __('lynx-forms::admin.submission.archive'), 'spam' => __('lynx-forms::admin.submission.spam'), 'new' => __('lynx-forms::admin.submission.mark_new')] as $value => $label)
                @if ($submission->status !== $value)
                    <x-lynx-admin::ui.button variant="outline" size="sm" wire:click="setStatus('{{ $value }}')">{{ $label }}</x-lynx-admin::ui.button>
                @endif
            @endforeach
            @if ($canDelete)
                <span x-data="{ confirming: false }" class="flex items-center gap-2">
                    <x-lynx-admin::ui.button variant="outline" size="sm" x-show="! confirming" x-on:click="confirming = true">{{ __('lynx-forms::admin.submission.delete') }}</x-lynx-admin::ui.button>
                    <span x-show="confirming" x-cloak class="flex items-center gap-2 text-sm">
                        <span class="text-destructive">{{ __('lynx-forms::admin.submission.confirm') }}</span>
                        <x-lynx-admin::ui.button variant="destructive" size="sm" wire:click="delete">{{ __('lynx-forms::admin.submission.yes_delete') }}</x-lynx-admin::ui.button>
                        <x-lynx-admin::ui.button variant="ghost" size="sm" x-on:click="confirming = false">{{ __('lynx-forms::admin.submission.cancel') }}</x-lynx-admin::ui.button>
                    </span>
                </span>
            @endif
        </div>
    </div>

    <section class="bg-card text-card-foreground divide-y rounded-xl border shadow-sm">
        @foreach ($lines as $line)
            @continue($submission->files->contains('field', $line['name']))
            <div class="grid gap-1 p-5 sm:grid-cols-[14rem_minmax(0,1fr)] sm:gap-6">
                <p class="text-muted-foreground text-sm">{{ $line['label'] }}</p>
                {{-- No whitespace inside: pre-line would show the template's own line breaks. --}}
                @if (filter_var($line['value'], FILTER_VALIDATE_EMAIL))
                    <p class="break-words"><a href="mailto:{{ $line['value'] }}" class="underline">{{ $line['value'] }}</a></p>
                @else
                    <p class="whitespace-pre-line break-words">{{ $line['value'] }}</p>
                @endif
            </div>
        @endforeach
        @if ($submission->files->isNotEmpty())
            <div class="grid gap-1 p-5 sm:grid-cols-[14rem_minmax(0,1fr)] sm:gap-6">
                <p class="text-muted-foreground text-sm">{{ __('lynx-forms::admin.submission.files') }}</p>
                <ul class="grid gap-1">
                    @foreach ($submission->files as $file)
                        <li><a href="{{ route('lynx.admin.forms.file', [$submission->uuid, $file->id]) }}" class="underline">{{ $file->name }}</a> <span class="text-muted-foreground text-xs">{{ \Illuminate\Support\Number::fileSize($file->bytes) }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    <section class="grid gap-2">
        <label for="submission-notes" class="font-medium">{{ __('lynx-forms::admin.submission.notes') }}</label>
        <textarea id="submission-notes" rows="3" class="blat-textarea" wire:model="notes" placeholder="{{ __('lynx-forms::admin.submission.notes_placeholder') }}"></textarea>
        <div><x-lynx-admin::ui.button size="sm" wire:click="saveNotes">{{ __('lynx-forms::admin.submission.save_notes') }}</x-lynx-admin::ui.button></div>
    </section>
</div>
