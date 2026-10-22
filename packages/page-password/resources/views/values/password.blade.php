{{-- A page password shown read only: whether one is set, never the password. --}}
@if ((new \LynxCms\PagePassword\StoredPassword(is_string($value) ? $value : null))->isSet())
    <span class="text-sm">Set</span>
@else
    <span class="text-muted-foreground text-sm">Not set</span>
@endif
