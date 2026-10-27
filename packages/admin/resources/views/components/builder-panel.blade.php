@props(['title', 'description' => null, 'icon' => null])
{{-- One purpose of the field builder (a block type's name, its fields, how editors see it) on a gray panel. Inputs inside stay white. --}}
<section aria-label="{{ $title }}" {{ $attributes->class('bg-muted/60 grid min-w-0 gap-4 rounded-lg p-5 [&_.blat-input]:bg-background [&_.blat-select]:bg-background [&_.blat-textarea]:bg-background') }}>
    <header class="grid gap-1">
        <h3 class="flex items-center gap-2 text-sm font-semibold">
            @if ($icon) <x-dynamic-component :component="'lucide-'.$icon" class="text-muted-foreground size-4" aria-hidden="true" /> @endif
            {{ $title }}
        </h3>
        @if ($description) <p class="text-muted-foreground text-sm">{{ $description }}</p> @endif
    </header>
    {{ $slot }}
</section>
