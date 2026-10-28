@props(['field', 'index', 'subfield', 'model', 'values', 'disabled' => false, 'pageId' => null])
{{-- One subfield of a repeater or matrix item: its input per language (compact tabs when translatable). --}}
@php
    $input = app(\LynxCms\Admin\Editor\FieldInputs::class)->for((string) $subfield->field->type);
    $codes = array_keys($values);
    $id = $field.'-'.$index.'-'.$subfield->name();
    $view = new \LynxCms\Admin\Editor\FieldView($id, $subfield->label(), (string) $subfield->field->type, $subfield->isTranslatable(), $subfield->isRequired(), $subfield->description() ?: null, [], $subfield->config(), $subfield->notes() ?: null, $pageId, $subfield->name());
    $span = max(3, min(12, (int) round($subfield->width() * 12 / 100)));
    $errorKey = 'values.'.$field.'.'.$index.'.'.$subfield->name();
    // Read-only displays expect prepared values (page references as [id, title] pairs), not form values.
    $readOnly = fn (mixed $value): mixed => $disabled || ! $input ? app(\LynxCms\Admin\Editor\FieldInputs::class)->prepareDisplay((string) $subfield->field->type, $value) : $value;
@endphp
<div class="col-span-12 grid content-start gap-1 sm:[grid-column:span_var(--span)/span_var(--span)]" style="--span: {{ $span }}">
    <p id="field-{{ $id }}" class="text-sm font-medium">{{ $subfield->label() }}@if ($subfield->isRequired())<span class="text-destructive" aria-label="required"> *</span>@endif</p>
    @if ($subfield->description())
        <p class="text-muted-foreground text-xs">{{ $subfield->description() }}</p>
    @endif
    @if (! $input)
        <x-lynx-admin::editor.value :type="$subfield->field->type" :value="$readOnly(reset($values))" />
    @elseif (count($codes) > 1)
        <x-lynx-admin::ui.tabs :value="$codes[0]" class="gap-1">
            <x-lynx-admin::ui.tabs-list variant="compact" aria-label="Languages">
                @foreach (\LynxCms\Admin\Editor\LocalizedForm::languages() as $language)
                    <x-lynx-admin::ui.tabs-trigger :value="$language['code']">{{ $language['label'] }}</x-lynx-admin::ui.tabs-trigger>
                @endforeach
            </x-lynx-admin::ui.tabs-list>
            @foreach ($codes as $code)
                <div x-show="tab === '{{ $code }}'" @if (! $loop->first) x-cloak @endif>
                    @include($input, ['field' => $view, 'code' => $code, 'model' => $model.'.'.$code, 'placeholder' => '', 'disabled' => $disabled, 'live' => false, 'value' => $values[$code] ?? null, 'readOnly' => $readOnly($values[$code] ?? null)])
                </div>
            @endforeach
        </x-lynx-admin::ui.tabs>
    @else
        @include($input, ['field' => $view, 'code' => $codes[0] ?? 'default', 'model' => $model.'.'.($codes[0] ?? 'default'), 'placeholder' => '', 'disabled' => $disabled, 'live' => false, 'value' => $values[$codes[0] ?? ''] ?? null, 'readOnly' => $readOnly($values[$codes[0] ?? ''] ?? null)])
    @endif
    @if ($subfield->notes())
        <p class="text-muted-foreground text-xs">{{ $subfield->notes() }}</p>
    @endif
    @error($errorKey) <p role="alert" class="text-destructive text-sm">{{ $message }}</p> @enderror
    @foreach ($codes as $code)
        @error($errorKey.'.'.$code) <p role="alert" class="text-destructive text-sm">{{ strtoupper($code) }}: {{ $message }}</p> @enderror
    @endforeach
</div>
