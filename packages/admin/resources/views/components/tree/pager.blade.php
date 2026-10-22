@props(['level', 'depth', 'state'])
@php
    $isMoving = $state['sortable'] && $level->isComplete && $state['moving'] !== null;
    $links = [];

    if ($level->page > 1) {
        $links[] = ['label' => '‹', 'page' => $level->page - 1, 'aria' => 'Previous page', 'current' => false];
    }

    foreach ($level->pagerLinks() as $number) {
        $links[] = ['label' => $number ?? '…', 'page' => $number, 'aria' => "Page {$number}", 'current' => $number === $level->page];
    }

    if ($level->page < $level->lastPage()) {
        $links[] = ['label' => '›', 'page' => $level->page + 1, 'aria' => 'Next page', 'current' => false];
    }
@endphp
<nav class="lynx-tree-row lynx-tree-pager" style="--depth: {{ $depth }}" aria-label="Pages of children">
    <ul class="ms-7 flex flex-wrap items-center gap-1">
        @foreach ($links as $link)
            <li>
                @if ($link['page'] === null)
                    <span class="px-1.5 text-sm text-muted-foreground" aria-hidden="true">…</span>
                @elseif ($link['current'])
                    <x-lynx-admin::ui.button as="span" size="icon-sm" class="tabular-nums" aria-current="page">{{ $link['label'] }}</x-lynx-admin::ui.button>
                @elseif ($isMoving)
                    {{-- In move mode an empty list covers the link while dragging: dropping on it places the page at the top of that pager page. --}}
                    <span class="relative block">
                        <x-lynx-admin::ui.button variant="outline" size="icon-sm" class="tabular-nums" wire:click="goToPage({{ $level->parentId }}, {{ $link['page'] }})" aria-label="{{ $link['aria'] }}">{{ $link['label'] }}</x-lynx-admin::ui.button>
                        <ul
                            class="lynx-pager-drop"
                            aria-hidden="true"
                            wire:sort="handleSort"
                            wire:sort:group="lynx-tree"
                            wire:sort:group-id="{{ $level->parentId }}:{{ $link['page'] }}"
                            wire:sort:config="{{ \LynxCms\Admin\Livewire\PageTree::SORTABLE_CONFIG }}"
                        ></ul>
                    </span>
                @else
                    <x-lynx-admin::ui.button variant="outline" size="icon-sm" class="tabular-nums" wire:click="goToPage({{ $level->parentId }}, {{ $link['page'] }})" aria-label="{{ $link['aria'] }}">{{ $link['label'] }}</x-lynx-admin::ui.button>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
