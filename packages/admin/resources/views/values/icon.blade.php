{{-- Icon field shown read only: the icon and its name; a name the set no longer has shows as text. --}}
@if (is_string($value) && app(\LynxCms\Admin\Icons\IconSet::class)->has($value))
    <span class="inline-flex items-center gap-2"><x-dynamic-component :component="'lucide-'.$value" class="size-4" aria-hidden="true" /><span class="font-mono text-sm">{{ $value }}</span></span>
@elseif (is_string($value) && $value !== '')
    <span class="font-mono text-sm">{{ $value }}</span>
@endif
