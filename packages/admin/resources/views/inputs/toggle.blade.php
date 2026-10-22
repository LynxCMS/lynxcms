<label class="inline-flex items-center gap-2 text-sm">
    <input class="blat-checkbox" type="checkbox" wire:model="{{ $model }}" aria-labelledby="field-{{ $field->name }}" @disabled($disabled)>
    Yes
</label>
