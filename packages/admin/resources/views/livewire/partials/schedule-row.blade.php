{{-- One row of the page schedule: $label, $model ("all" or "times.{code}") and $key. --}}
<div @class(['grid items-start gap-2', 'sm:grid-cols-[10rem_1fr_1fr]' => $label !== '', 'sm:grid-cols-2' => $label === '']) wire:key="{{ $key }}">
    @if ($label !== '')<span class="pt-2 text-sm font-medium">{{ $label }}</span>@endif
    @foreach (['publish' => __('lynx-admin::settings.schedule.publish_at'), 'unpublish' => __('lynx-admin::settings.schedule.unpublish_at')] as $action => $actionLabel)
        <label class="grid gap-1 text-sm">
            <span class="text-muted-foreground">{{ $actionLabel }}</span>
            <input type="datetime-local" class="blat-input" wire:model="{{ $model }}.{{ $action }}" @disabled(! $canPublish) @error("{$model}.{$action}") aria-invalid="true" @enderror>
            @error("{$model}.{$action}") <span role="alert" class="text-destructive text-sm">{{ $message }}</span> @enderror
        </label>
    @endforeach
</div>
