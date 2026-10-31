<div class="space-y-6">
    {{-- Redirects --}}
    @if ($hasUrl)
    <section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="settings-redirects">
        <h2 id="settings-redirects" class="mb-1 text-base font-semibold">{{ __('lynx-admin::settings.redirects.title') }}</h2>
        <p class="text-muted-foreground text-sm mb-4">{{ __('lynx-admin::settings.redirects.intro') }}</p>
        @forelse ($redirects as $old)
            @if ($loop->first) <ul class="divide-y divide-border text-sm"> @endif
            <li class="flex flex-wrap items-baseline justify-between gap-2 py-2" wire:key="redirect-{{ $old->language->code }}-{{ $old->path }}" x-data="{ confirming: false }">
                <span><code>{{ $old->url() }}</code> <span class="text-muted-foreground">{{ $old->language->name }}</span></span>
                <span class="flex items-baseline gap-3">
                    <span class="{{ $old->isRedirecting ? 'text-muted-foreground' : 'text-destructive mt-1 text-sm' }}">{{ __($old->isRedirecting ? 'lynx-admin::settings.redirects.redirects' : 'lynx-admin::settings.redirects.taken') }}</span>
                    @unless ($isLocked)
                        <button type="button" class="text-primary font-medium underline-offset-4 hover:underline" x-show="! confirming" @click="confirming = true">{{ __('lynx-admin::settings.redirects.delete') }}</button>
                        <span x-show="confirming" x-cloak>
                            {{ __('lynx-admin::settings.redirects.confirm') }}
                            <button type="button" class="font-medium underline-offset-4 hover:underline text-destructive" wire:click="deleteRedirect(@js($old->language->code), @js($old->path))">{{ __('lynx-admin::settings.redirects.yes_delete') }}</button>
                            <button type="button" class="text-primary font-medium underline-offset-4 hover:underline" @click="confirming = false">{{ __('lynx-admin::settings.redirects.cancel') }}</button>
                        </span>
                    @endunless
                </span>
            </li>
            @if ($loop->last) </ul> @endif
        @empty
            <p class="text-muted-foreground text-sm">{{ __('lynx-admin::settings.redirects.empty') }}</p>
        @endforelse
        @error('path') <p role="alert" class="text-destructive text-sm mt-2">{{ $message }}</p> @enderror
        @error('page') <p role="alert" class="text-destructive text-sm mt-2">{{ $message }}</p> @enderror
    </section>
    @endif

    {{-- Backlinks --}}
    <section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="settings-backlinks">
        <h2 id="settings-backlinks" class="mb-1 text-base font-semibold">{{ __('lynx-admin::settings.backlinks.title') }}</h2>
        <p class="text-muted-foreground text-sm mb-4">{{ __('lynx-admin::settings.backlinks.intro') }}</p>
        @forelse ($backlinks as $link)
            @if ($loop->first) <ul class="divide-y divide-border text-sm"> @endif
            <li class="flex flex-wrap items-baseline justify-between gap-2 py-2" wire:key="backlink-{{ $link['id'] }}">
                <a href="{{ route('lynx.admin.pages.edit', $link['id']) }}" class="text-primary font-medium underline-offset-4 hover:underline">{{ $link['title'] }}</a>
                <span class="text-muted-foreground">{{ $link['context'] }}</span>
            </li>
            @if ($loop->last) </ul> @endif
        @empty
            <p class="text-muted-foreground text-sm">{{ __('lynx-admin::settings.backlinks.empty') }}</p>
        @endforelse
    </section>

    {{-- Activity --}}
    <section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="settings-activity">
        <h2 id="settings-activity" class="mb-4 text-base font-semibold">{{ __('lynx-admin::settings.activity.title') }}</h2>
        @forelse ($activity as $entry)
            @if ($loop->first) <ol class="divide-y divide-border text-sm"> @endif
            <li class="flex flex-wrap items-baseline gap-x-3 gap-y-1 py-2">
                <time class="w-28 shrink-0 text-muted-foreground" title="{{ $entry['at'] }}">{{ $entry['when'] }}</time>
                <span class="font-medium">{{ $entry['who'] }}</span>
                <span class="min-w-0 flex-1"><x-lynx-admin::activity-summary :what="$entry['what']" /></span>
            </li>
            @if ($loop->last) </ol> @endif
        @empty
            <p class="text-muted-foreground text-sm">{{ __('lynx-admin::settings.activity.empty') }}</p>
        @endforelse
        @if ($hasMoreActivity)
            <x-lynx-admin::ui.button variant="outline" type="button" wire:click="showMoreActivity" class="mt-3">{{ __('lynx-admin::settings.activity.more') }}</x-lynx-admin::ui.button>
        @endif
    </section>
</div>
