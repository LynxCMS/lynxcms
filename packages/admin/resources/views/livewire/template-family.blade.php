{{-- Part of template-form: family settings (ADR-0032), pages and delete. --}}
@php($others = array_values(array_diff($templates, [$template])))
<section class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-5 p-5" aria-labelledby="template-family">
    <h2 id="template-family" class="text-base font-semibold">Family</h2>

    <div class="grid gap-2 text-sm">
        <label class="flex items-center gap-2"><input class="blat-checkbox" id="family-has-url" type="checkbox" wire:model="hasUrl"> Pages have a URL <span class="text-muted-foreground">(off for settings and reusable content)</span></label>
        <label class="flex items-center gap-2"><input class="blat-checkbox" id="family-singleton" type="checkbox" wire:model="singleton"> Only one page may use this template</label>
        <label class="flex items-center gap-2"><input class="blat-checkbox" id="family-allow-children" type="checkbox" wire:model.live="allowChildren"> Pages may have children</label>
    </div>

    @foreach ([
        'childTemplates' => ['Templates allowed for children', 'None checked: any template.', $allowChildren],
        'parentTemplates' => ['Templates allowed for the parent', 'None checked: any template.', true],
        'switchTemplates' => ['Templates pages may switch to', 'None checked: any template.', true],
    ] as $property => [$legend, $hint, $isShown])
        @if ($isShown)
            <fieldset class="grid gap-2">
                <legend class="mb-1 text-sm font-medium">{{ $legend }}</legend>
                <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm">
                    @foreach ($others as $option)
                        <label class="flex items-center gap-2"><input class="blat-checkbox" id="family-{{ $property }}-{{ $option }}" type="checkbox" value="{{ $option }}" wire:model="{{ $property }}"> {{ $option }}</label>
                    @endforeach
                </div>
                <p class="text-muted-foreground text-sm">{{ $hint }}</p>
            </fieldset>
        @endif
    @endforeach

    @foreach (['has_url', 'singleton', 'allow_children', 'child_templates', 'parent_templates', 'allowed_templates'] as $key)
        @error($key) <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
    @endforeach
    @if ($canEdit) <div><x-lynx-admin::ui.button variant="outline" type="button" wire:click="saveFamily">Save family</x-lynx-admin::ui.button></div> @endif
</section>

<section class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-3 p-5" aria-labelledby="template-pages">
    <h2 id="template-pages" class="text-base font-semibold">Pages</h2>
    <p class="text-sm">{{ match (true) { $pageCount === 0 => 'No page uses this template.', $pageCount === 1 => '1 page uses this template.', default => $pageCount.' pages use this template.' } }}</p>

    @error('template') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror

    @if ($isConfirmingDelete)
        <div role="alert" class="grid gap-3">
            <ul class="list-disc ps-5 text-sm">
                @foreach ($deleteSummary as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
            <div class="flex flex-wrap gap-3">
                <x-lynx-admin::ui.button variant="destructive" type="button" wire:click="delete">Delete template {{ $template }}</x-lynx-admin::ui.button>
                <x-lynx-admin::ui.button variant="outline" type="button" wire:click="cancelDelete">Cancel</x-lynx-admin::ui.button>
            </div>
        </div>
    @elseif ($canEdit)
        <div><x-lynx-admin::ui.button variant="outline" type="button" wire:click="confirmDelete" class="text-destructive hover:text-destructive">Delete template</x-lynx-admin::ui.button></div>
    @endif
</section>
