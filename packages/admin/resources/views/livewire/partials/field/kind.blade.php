{{-- What the field holds: kinds in groups on a new field (FieldKinds), folded to the chosen one after a choice. --}}
<section class="grid gap-4" aria-labelledby="field-kind-heading">
    <h2 id="field-kind-heading" class="text-base font-semibold">What it holds</h2>
    @if ($field === null)
        <div class="grid gap-4" x-data="{ open: true }" role="radiogroup" aria-labelledby="field-kind-heading">
            @foreach ($kinds as $group => $groupKinds)
                <div class="grid gap-1.5" data-kind-group x-show="open || {{ Js::from(array_keys($groupKinds)) }}.includes($wire.type)">
                    <span class="text-muted-foreground text-xs font-medium" x-show="open">{{ $group }}</span>
                    <div class="grid gap-1.5 sm:grid-cols-2">
                        @foreach ($groupKinds as $typeName => $info)
                            <label data-kind="{{ $typeName }}" x-show="open || $wire.type === {{ Js::from($typeName) }}" x-on:click="open = false"
                                   class="has-checked:border-primary has-checked:bg-primary/5 has-checked:ring-primary group/kind relative flex cursor-pointer items-center gap-3 rounded-lg border p-2.5 has-checked:ring-1">
                                <input type="radio" class="sr-only" name="field-type" value="{{ $typeName }}" wire:model.live="type">
                                <span class="bg-muted text-muted-foreground group-has-checked/kind:bg-primary group-has-checked/kind:text-primary-foreground grid size-8 shrink-0 place-items-center rounded-md">
                                    <x-dynamic-component :component="'lucide-'.$info['icon']" class="size-4" aria-hidden="true" />
                                </span>
                                <span class="grid min-w-0 gap-0.5 text-sm"><span class="font-medium">{{ $info['label'] }}</span><span class="text-muted-foreground text-xs">{{ $info['description'] }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
            <x-lynx-admin::ui.button variant="ghost" size="sm" type="button" class="justify-self-start" x-show="! open" x-on:click="open = true">Change what it holds</x-lynx-admin::ui.button>
        </div>
        @error('type') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
    @else
        <p class="flex items-center gap-3 text-sm" data-field-kind="{{ $type }}">
            <span class="bg-muted text-muted-foreground grid size-8 place-items-center rounded-md"><x-dynamic-component :component="'lucide-'.$kind['icon']" class="size-4" aria-hidden="true" /></span>
            <span class="grid gap-0.5"><span class="font-medium">{{ $kind['label'] }}</span><span class="text-muted-foreground text-xs">{{ $kind['description'] }} It can't change once the field exists.</span></span>
        </p>
    @endif
</section>
