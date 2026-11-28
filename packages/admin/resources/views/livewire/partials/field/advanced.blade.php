{{-- The Advanced tab: loading with every page, locking for the site's code (ADR-0149), then deleting the field. --}}
<div class="grid gap-8">
    <section class="grid gap-3" aria-label="{{ __('lynx-admin::fields.advanced.heading') }}" data-field-advanced>
        @if ($name === 'title')
            <p class="text-sm">{{ __('lynx-admin::fields.advanced.always') }}</p>
        @else
            <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm">
                <input class="blat-checkbox mt-0.5" id="field-autoload" type="checkbox" wire:model="autoload">
                <span class="grid gap-0.5"><span class="font-medium">{{ __('lynx-admin::fields.advanced.autoload') }}</span><span class="text-muted-foreground">{{ __('lynx-admin::fields.advanced.autoload_hint') }}</span></span>
            </label>
        @endif
        @error('settings.autoload') <p data-error-for="settings.autoload" role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
        {{-- The title is required on every template, so it can't be locked (ADR-0149); only superusers lock fields. --}}
        @unless ($name === 'title' || ! $canProtect)
        <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm" data-field-locked>
            <input class="blat-checkbox mt-0.5" id="field-locked" type="checkbox" wire:model="locked">
            <span class="grid gap-0.5"><span class="font-medium">{{ __('lynx-admin::fields.advanced.locked') }}</span><span class="text-muted-foreground">{{ __('lynx-admin::fields.advanced.locked_hint') }}</span></span>
        </label>
        @endunless
        @error('settings.locked') <p data-error-for="settings.locked" role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
    </section>

    @include('lynx-admin::livewire.partials.field.danger')
</div>
