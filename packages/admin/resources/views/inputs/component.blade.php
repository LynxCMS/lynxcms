{{-- A component field: choose the component, then fill the settings its schema declares. --}}
@php
    $componentSettings = app(\LynxCms\Admin\Editor\ComponentSettings::class);
    $alias = (string) (($value ?? [])['component'] ?? '');
    $choices = $componentSettings->choices($field->settings);
@endphp
<div class="space-y-3">
    <select class="blat-select max-w-sm" wire:model.live="{{ $model }}.component" aria-labelledby="field-{{ $field->name }}" @disabled($disabled)>
        <option value="">None</option>
        @foreach ($choices as $choice => $description)
            <option value="{{ $choice }}">{{ $choice }}</option>
        @endforeach
    </select>
    @if (isset($choices[$alias]) && $choices[$alias] !== $alias)
        <p class="text-muted-foreground text-sm">{{ $choices[$alias] }}</p>
    @endif

    @if ($alias !== '')
        <div class="grid gap-3 sm:grid-cols-2">
            @forelse ($componentSettings->fields($alias) as $name => $setting)
                <div>
                    <label for="setting-{{ $field->name }}-{{ $name }}" class="blat-label mb-2">{{ str_replace('_', ' ', ucfirst($name)) }}</label>
                    @if ($setting['enum'] !== [])
                        <select id="setting-{{ $field->name }}-{{ $name }}" class="blat-select" wire:model="{{ $model }}.settings.{{ $name }}" @disabled($disabled)>
                            <option value=""></option>
                            @foreach ($setting['enum'] as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    @elseif ($setting['type'] === 'boolean')
                        <input id="setting-{{ $field->name }}-{{ $name }}" type="checkbox" wire:model="{{ $model }}.settings.{{ $name }}" @disabled($disabled)>
                    @elseif (in_array($setting['type'], ['integer', 'number'], true))
                        <input id="setting-{{ $field->name }}-{{ $name }}" type="number" class="blat-input" wire:model="{{ $model }}.settings.{{ $name }}" @disabled($disabled)>
                    @elseif ($setting['type'] === 'string')
                        <input id="setting-{{ $field->name }}-{{ $name }}" type="text" class="blat-input" wire:model="{{ $model }}.settings.{{ $name }}" @disabled($disabled)>
                    @else
                        <p class="text-muted-foreground text-sm">This {{ $setting['type'] }} setting can be changed through the API or MCP.</p>
                    @endif
                    @if ($setting['description'] !== '')
                        <p class="text-muted-foreground text-sm mt-1">{{ $setting['description'] }}</p>
                    @endif
                </div>
            @empty
                <p class="text-muted-foreground text-sm">This component has no settings.</p>
            @endforelse
        </div>
    @endif
</div>
