@props(['owner' => null])
{{-- Why a schema screen is read only: a module owns it, or schema editing is off (lynx-admin.schema_editing). --}}
<p role="status" class="bg-card text-card-foreground rounded-xl border shadow-sm p-4 text-sm">
    @if ($owner)
        Managed by the {{ $owner }} module, which depends on it. Change it through that module.
    @else
        Schema changes on this site come from the schema files: edit them in Git and run <code>php artisan lynx:schema:apply</code>.
    @endif
</p>
