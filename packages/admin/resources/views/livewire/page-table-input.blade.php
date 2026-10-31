{{-- The page table input: the field's pages with their columns, and a form to add one. --}}
<div class="grid gap-3">
    <x-lynx-admin::ui.table variant="card">
        <x-lynx-admin::ui.table-header>
            <x-lynx-admin::ui.table-row class="hover:bg-transparent">
                @foreach ($labels as $label)
                    <x-lynx-admin::ui.table-head class="px-3 text-muted-foreground">{{ $label }}</x-lynx-admin::ui.table-head>
                @endforeach
                <x-lynx-admin::ui.table-head class="px-3"><span class="sr-only">{{ __('lynx-admin::inputs.page_table.actions') }}</span></x-lynx-admin::ui.table-head>
            </x-lynx-admin::ui.table-row>
        </x-lynx-admin::ui.table-header>
        <x-lynx-admin::ui.table-body>
            @forelse ($rows as $index => $row)
                <x-lynx-admin::ui.table-row wire:key="page-table-row-{{ $row['id'] }}">
                    @foreach ($row['cells'] as $cell)
                        <x-lynx-admin::ui.table-cell class="whitespace-normal px-3 py-2">{{ $cell }}</x-lynx-admin::ui.table-cell>
                    @endforeach
                    <x-lynx-admin::ui.table-cell class="px-3 py-2 text-end">
                        <span class="inline-flex gap-1">
                            <x-lynx-admin::ui.button variant="outline" size="xs" :href="route('lynx.admin.pages.edit', $row['id'])" target="_blank">{{ __('lynx-admin::inputs.page_table.edit') }}</x-lynx-admin::ui.button>
                            @unless ($disabled)
                                <x-lynx-admin::ui.button variant="ghost" size="xs" wire:click="move({{ $row['id'] }}, -1)" :disabled="$index === 0" aria-label="{{ __('lynx-admin::inputs.page_table.move_up') }}">↑</x-lynx-admin::ui.button>
                                <x-lynx-admin::ui.button variant="ghost" size="xs" wire:click="move({{ $row['id'] }}, 1)" :disabled="$index === count($rows) - 1" aria-label="{{ __('lynx-admin::inputs.page_table.move_down') }}">↓</x-lynx-admin::ui.button>
                                @if ($trashOnRemove)
                                    <x-lynx-admin::ui.button variant="ghost" size="xs" wire:click="remove({{ $row['id'] }})" wire:confirm="{{ __('lynx-admin::inputs.page_table.confirm_remove') }}">{{ __('lynx-admin::inputs.page_table.remove') }}</x-lynx-admin::ui.button>
                                @else
                                    <x-lynx-admin::ui.button variant="ghost" size="xs" wire:click="remove({{ $row['id'] }})">{{ __('lynx-admin::inputs.page_table.remove') }}</x-lynx-admin::ui.button>
                                @endif
                            @endunless
                        </span>
                    </x-lynx-admin::ui.table-cell>
                </x-lynx-admin::ui.table-row>
            @empty
                <x-lynx-admin::ui.table-row><x-lynx-admin::ui.table-cell :colspan="count($labels) + 1" class="whitespace-normal px-3 py-4 text-center text-muted-foreground">{{ __('lynx-admin::inputs.page_table.empty') }}</x-lynx-admin::ui.table-cell></x-lynx-admin::ui.table-row>
            @endforelse
        </x-lynx-admin::ui.table-body>
    </x-lynx-admin::ui.table>

    @if ($canCreate)
        <div class="flex flex-wrap items-end gap-2">
            @if (count($templates) > 1)
                <select class="blat-select w-44" wire:model="template" aria-label="{{ __('lynx-admin::inputs.page_table.template') }}">
                    @foreach ($templates as $name)
                        <option value="{{ $name }}">{{ lynx()->templates->find($name)?->label ?? $name }}</option>
                    @endforeach
                </select>
            @endif
            <input type="text" class="blat-input w-64" wire:model="title" wire:keydown.enter.prevent="create" placeholder="{{ __('lynx-admin::inputs.page_table.title') }}" aria-label="{{ __('lynx-admin::inputs.page_table.title') }}">
            <x-lynx-admin::ui.button type="button" variant="outline" size="sm" wire:click="create">{{ __('lynx-admin::inputs.page_table.add') }}</x-lynx-admin::ui.button>
        </div>
        @error('title') <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
    @elseif (! $disabled)
        <p class="text-muted-foreground text-sm">{{ __('lynx-admin::inputs.page_table.save_first') }}</p>
    @endif
</div>
