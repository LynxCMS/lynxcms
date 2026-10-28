{{-- Rules for the web server, so it sends stored pages without starting PHP. --}}
<div class="grid gap-5">
    <p class="text-muted-foreground max-w-2xl text-sm">With these rules the web server sends stored pages itself, without PHP. Without them, PHP still sends stored pages before the session starts, only a little slower.</p>
    <div class="flex flex-wrap items-center gap-3">
        <label for="cache-server" class="text-sm font-medium">Server</label>
        <select id="cache-server" class="blat-select w-40" wire:model.live="server">
            @foreach ($servers as $name)
                <option value="{{ $name }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <pre class="bg-muted overflow-x-auto rounded-lg p-4 font-mono text-xs leading-relaxed">{{ $serverText }}</pre>
</div>
