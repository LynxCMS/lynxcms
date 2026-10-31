@props(['owner' => null])
{{-- Why a schema screen is read only: a module owns it, or schema editing is off (lynx-admin.schema_editing). --}}
<p role="status" class="bg-card text-card-foreground rounded-xl border shadow-sm p-4 text-sm">
    @if ($owner)
        {{ __('lynx-admin::fields.readonly.module', ['module' => $owner]) }}
    @else
        {!! __('lynx-admin::fields.readonly.files', ['command' => '<code>php artisan lynx:schema:apply</code>']) !!}
    @endif
</p>
