<div class="space-y-4">
    <p class="text-muted-foreground max-w-prose text-sm">With SEO on, a template's pages get their own SEO title, description, share image, noindex switch and canonical URL. Pages that leave them empty use their title, their content and the site settings.</p>

    <x-lynx-admin::ui.table variant="card">
        <x-lynx-admin::ui.table-header>
            <x-lynx-admin::ui.table-row class="hover:bg-transparent">
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">Template</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">Pages</x-lynx-admin::ui.table-head>
                <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">SEO fields</x-lynx-admin::ui.table-head>
            </x-lynx-admin::ui.table-row>
        </x-lynx-admin::ui.table-header>
        <x-lynx-admin::ui.table-body>
            @forelse ($rows as $row)
                <x-lynx-admin::ui.table-row wire:key="seo-template-{{ $row['name'] }}">
                    <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">
                        <span class="font-medium">{{ $row['label'] }}</span>
                        <span class="text-muted-foreground ms-1 font-mono text-xs">{{ $row['name'] }}</span>
                    </x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="px-4 py-2 tabular-nums">{{ $row['pages'] }}</x-lynx-admin::ui.table-cell>
                    <x-lynx-admin::ui.table-cell class="px-4 py-2">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" class="blat-checkbox" @checked($row['isOn']) wire:click="toggle(@js($row['name']))" wire:loading.attr="disabled">
                            {{ $row['isOn'] ? 'On' : 'Off' }}
                        </label>
                    </x-lynx-admin::ui.table-cell>
                </x-lynx-admin::ui.table-row>
            @empty
                <x-lynx-admin::ui.table-row><x-lynx-admin::ui.table-cell colspan="3" class="whitespace-normal px-4 py-6 text-center text-muted-foreground">No templates with URLs yet.</x-lynx-admin::ui.table-cell></x-lynx-admin::ui.table-row>
            @endforelse
        </x-lynx-admin::ui.table-body>
    </x-lynx-admin::ui.table>
</div>
