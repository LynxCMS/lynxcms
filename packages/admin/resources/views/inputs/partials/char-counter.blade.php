{{-- Characters typed against the field's "max_length", under its input: "48/60", red past the limit. It only
     informs; the save refuses a longer text. Counts code points, as the server's limit does. --}}
<p class="text-muted-foreground text-end text-xs tabular-nums" data-char-counter="{{ $field->name }}" data-char-max="{{ $maxLength }}"
    x-bind:class="count > {{ $maxLength }} && 'text-destructive'">
    <span x-text="count">0</span>/{{ $maxLength }}<span class="sr-only"> {{ __('lynx-admin::inputs.characters') }}</span>
</p>
