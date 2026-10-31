{{-- A page password shown read only: whether one is set, never the password. --}}
@if ((new \LynxCms\PagePassword\StoredPassword(is_string($value) ? $value : null))->isSet())
    <span class="text-sm">{{ __('lynx-page-password::admin.set') }}</span>
@else
    <span class="text-muted-foreground text-sm">{{ __('lynx-page-password::admin.not_set') }}</span>
@endif
