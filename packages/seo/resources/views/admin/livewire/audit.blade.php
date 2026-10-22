<div class="space-y-4">
    <p class="text-muted-foreground text-sm">Checked {{ $checked }} published {{ $checked === 1 ? 'page' : 'pages' }} in this language. {{ count($rows) === 0 ? 'Nothing to fix.' : '' }}</p>

    @if ($rows !== [])
        <x-lynx-admin::ui.table variant="card">
            <x-lynx-admin::ui.table-header>
                <x-lynx-admin::ui.table-row class="hover:bg-transparent">
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">Page</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4 text-muted-foreground">To fix</x-lynx-admin::ui.table-head>
                    <x-lynx-admin::ui.table-head class="px-4"><span class="sr-only">Actions</span></x-lynx-admin::ui.table-head>
                </x-lynx-admin::ui.table-row>
            </x-lynx-admin::ui.table-header>
            <x-lynx-admin::ui.table-body>
                @foreach ($rows as $row)
                    <x-lynx-admin::ui.table-row wire:key="seo-audit-{{ $row['id'] }}">
                        <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">
                            <span class="font-medium">{{ $row['title'] }}</span>
                            <span class="text-muted-foreground block font-mono text-xs">{{ $row['url'] }}</span>
                        </x-lynx-admin::ui.table-cell>
                        <x-lynx-admin::ui.table-cell class="whitespace-normal px-4 py-2">
                            <span class="flex flex-wrap gap-1">
                                @foreach ($row['issues'] as $issue)
                                    <x-lynx-admin::ui.badge variant="outline">{{ $issue }}</x-lynx-admin::ui.badge>
                                @endforeach
                            </span>
                        </x-lynx-admin::ui.table-cell>
                        <x-lynx-admin::ui.table-cell class="px-4 py-2 text-end">
                            <span class="inline-flex gap-2">
                                <x-lynx-admin::ui.button variant="outline" size="xs" :href="route('lynx.admin.pages.edit', $row['id'])">Edit</x-lynx-admin::ui.button>
                                <x-lynx-admin::ui.button variant="outline" size="xs" :href="route('lynx.admin.seo.preview', $row['id'])">Preview</x-lynx-admin::ui.button>
                            </span>
                        </x-lynx-admin::ui.table-cell>
                    </x-lynx-admin::ui.table-row>
                @endforeach
            </x-lynx-admin::ui.table-body>
        </x-lynx-admin::ui.table>
    @endif
</div>
