@props(['model', 'disabled' => false])
{{-- A list of choices: a value per row and a label in each language (ChoicesForm). The list lives in
     Alpine and syncs with the Livewire property at $model. Choice field options and form options use it. --}}
@php($languages = \LynxCms\Admin\Editor\LocalizedForm::languages())
<div data-options-editor class="grid gap-2"
     x-data="{
        rows: $wire.entangle('{{ $model }}'),
        tab: {{ Js::from($languages[0]['code'] ?? 'default') }},
        add() { this.rows = [...(this.rows || []), { value: '', label: Object.fromEntries({{ Js::from(array_column($languages, 'code')) }}.map(c => [c, ''])) }] },
        remove(i) { this.rows = this.rows.filter((_, k) => k !== i) },
        move(i, by) { const r = [...this.rows]; const j = i + by; if (j < 0 || j >= r.length) return; [r[i], r[j]] = [r[j], r[i]]; this.rows = r },
     }">
    @if (count($languages) > 1)
        <div class="flex flex-wrap gap-1" role="tablist" aria-label="Label language">
            @foreach ($languages as $language)
                <button type="button" role="tab" class="text-xxs rounded px-1.5 py-0.5" :class="tab === '{{ $language['code'] }}' ? 'bg-accent font-semibold' : 'text-muted-foreground'"
                        x-on:click="tab = '{{ $language['code'] }}'" :aria-selected="tab === '{{ $language['code'] }}'">{{ $language['label'] }}</button>
            @endforeach
        </div>
    @endif
    <template x-for="(row, index) in (rows || [])" :key="index">
        <div class="flex flex-wrap items-center gap-2">
            <input type="text" class="blat-input w-36 font-mono text-xs" x-model="row.value" placeholder="from the label" aria-label="Value" @disabled($disabled)>
            @foreach ($languages as $language)
                <input type="text" class="blat-input min-w-0 flex-1" x-show="tab === '{{ $language['code'] }}'" x-model="row.label['{{ $language['code'] }}']"
                       placeholder="Label ({{ $language['label'] }})" aria-label="Label ({{ $language['label'] }})" @disabled($disabled)>
            @endforeach
            @unless ($disabled)
                <span class="flex gap-1">
                    <button type="button" class="text-muted-foreground hover:text-foreground px-1" x-on:click="move(index, -1)" aria-label="Move up">↑</button>
                    <button type="button" class="text-muted-foreground hover:text-foreground px-1" x-on:click="move(index, 1)" aria-label="Move down">↓</button>
                    <button type="button" class="text-destructive px-1 text-sm underline-offset-4 hover:underline" x-on:click="remove(index)">Remove</button>
                </span>
            @endunless
        </div>
    </template>
    @unless ($disabled)
        <div><x-lynx-admin::ui.button variant="outline" size="xs" x-on:click="add()">Add option</x-lynx-admin::ui.button></div>
    @endunless
</div>
