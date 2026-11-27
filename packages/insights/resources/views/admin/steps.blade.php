{{-- Each step of an approved plan with its page and how far it got: done, waiting, or failed with its error (ADR-0157). --}}
@php($titles = array_column($links, 'title', 'id'))
<ol class="mb-4 divide-y divide-border rounded-lg border text-sm" data-insight-steps>
    @foreach ($steps as $index => $step)
        @php($arguments = $insight->plan[$index]['arguments'] ?? [])
        @php($page = is_int($arguments['page'] ?? null) ? $arguments['page'] : (is_int($arguments['id'] ?? null) ? $arguments['id'] : null))
        @php($icon = ['done' => 'circle-check', 'failed' => 'circle-x', 'stopped' => 'circle-slash'][$step['status']] ?? 'clock')
        <li class="flex items-start gap-3 px-3 py-2" wire:key="insight-{{ $insight->id }}-step-{{ $index }}" data-insight-step="{{ $index }}" data-step-status="{{ $step['status'] }}" data-step-page="{{ $page }}">
            <x-lynx-admin::ui.icon :name="$icon" @class(['mt-0.5 size-4 shrink-0', 'text-primary' => $step['status'] === 'done', 'text-destructive' => $step['status'] === 'failed', 'text-muted-foreground' => $step['status'] === 'pending']) aria-hidden="true" />
            <span class="min-w-0 flex-1">
                @if ($page !== null && isset($titles[$page]))
                    <a href="{{ route('lynx.admin.pages.edit', $page) }}" class="font-medium underline-offset-4 hover:underline">{{ $titles[$page] }}</a>
                @else
                    <span class="font-medium">{{ __('lynx-insights::admin.steps.step', ['number' => $index + 1]) }}</span>
                @endif
                @if (($step['error'] ?? '') !== '')
                    <span class="text-destructive block" data-step-error>{{ $step['error'] }}</span>
                @endif
            </span>
            <span class="text-muted-foreground flex shrink-0 items-center gap-3">
                @if ($step['status'] === 'failed')
                    @include('lynx-insights::admin.step-details', ['step' => $step, 'index' => $index, 'action' => $insight->plan[$index] ?? [], 'title' => $page !== null ? ($titles[$page] ?? null) : null])
                @endif
                {{ __('lynx-insights::admin.steps.'.$step['status']) }}
            </span>
        </li>
    @endforeach
</ol>
