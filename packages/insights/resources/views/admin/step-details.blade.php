{{-- A failed step's details in a dialog: the change it was making, when and after how many tries it failed, its error and, for superusers, its stack trace. --}}
<x-lynx-admin::ui.dialog wire:key="step-details-{{ $insight->id }}-{{ $index }}" data-step-details="{{ $index }}">
    <x-lynx-admin::ui.dialog-trigger>
        <x-lynx-admin::ui.button variant="outline" size="sm" type="button">{{ __('lynx-insights::admin.details.open') }}</x-lynx-admin::ui.button>
    </x-lynx-admin::ui.dialog-trigger>
    <x-lynx-admin::ui.dialog-content class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
        <x-lynx-admin::ui.dialog-header>
            <x-lynx-admin::ui.dialog-title>{{ $title ?? __('lynx-insights::admin.steps.step', ['number' => $index + 1]) }}</x-lynx-admin::ui.dialog-title>
            <x-lynx-admin::ui.dialog-description>{{ __('lynx-insights::admin.details.description', ['number' => $index + 1]) }}</x-lynx-admin::ui.dialog-description>
        </x-lynx-admin::ui.dialog-header>
        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
            <dt class="text-muted-foreground">{{ __('lynx-insights::admin.details.error') }}</dt>
            <dd class="text-destructive">{{ $step['error'] ?? '' }}</dd>
            @if (($step['exception'] ?? '') !== '')
                <dt class="text-muted-foreground">{{ __('lynx-insights::admin.details.exception') }}</dt>
                <dd class="font-mono text-xs break-all">{{ $step['exception'] }}</dd>
            @endif
            @if (isset($step['failed_at']))
                <dt class="text-muted-foreground">{{ __('lynx-insights::admin.details.failed_at') }}</dt>
                <dd>{{ \Illuminate\Support\Carbon::parse($step['failed_at'])->isoFormat('LLL') }}</dd>
            @endif
            @if (isset($step['tries']))
                <dt class="text-muted-foreground">{{ __('lynx-insights::admin.details.tries') }}</dt>
                <dd>{{ \LynxCms\Core\Support\LocalizedNumber::format($step['tries']) }}</dd>
            @endif
            <dt class="text-muted-foreground">{{ __('lynx-insights::admin.details.action') }}</dt>
            <dd class="font-mono text-xs">{{ $action['action'] ?? '' }}</dd>
        </dl>
        <pre class="bg-muted max-h-48 overflow-auto rounded-md p-3 text-xs" data-step-arguments>{{ json_encode($action['arguments'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        @if ($showsTraces && ($step['trace'] ?? '') !== '')
            <div class="grid gap-1">
                <p class="text-muted-foreground text-sm">{{ __('lynx-insights::admin.details.trace') }}</p>
                <pre class="bg-muted max-h-72 overflow-auto rounded-md p-3 text-xs whitespace-pre" data-step-trace>{{ $step['trace'] }}</pre>
            </div>
        @elseif (($step['trace'] ?? '') !== '')
            <p class="text-muted-foreground text-sm" data-step-trace-hidden>{{ __('lynx-insights::admin.details.trace_hidden') }}</p>
        @endif
        <x-lynx-admin::ui.dialog-footer>
            <x-lynx-admin::ui.dialog-close>
                <x-lynx-admin::ui.button variant="outline" type="button">{{ __('lynx-insights::admin.details.close') }}</x-lynx-admin::ui.button>
            </x-lynx-admin::ui.dialog-close>
        </x-lynx-admin::ui.dialog-footer>
    </x-lynx-admin::ui.dialog-content>
</x-lynx-admin::ui.dialog>
