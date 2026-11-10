{{-- What a home screen list shows when it has nothing to list: what would be here, and how it gets here. --}}
@props(['icon', 'title', 'help'])
<div class="flex items-start gap-3 rounded-lg border border-dashed p-4" data-home-empty>
    <span class="bg-muted text-muted-foreground flex size-8 shrink-0 items-center justify-center rounded-md" aria-hidden="true">
        <x-lynx-admin::ui.icon :name="$icon" class="size-4" />
    </span>
    <div class="grid gap-0.5 text-sm">
        <p class="font-medium">{{ $title }}</p>
        <p class="text-muted-foreground">{{ $help }}</p>
    </div>
</div>
