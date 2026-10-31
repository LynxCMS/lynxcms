{{-- Part of template-form: family settings (ADR-0032), pages and delete. --}}
@php($others = array_values(array_diff($templates, [$template])))
<section class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-5 p-5" aria-labelledby="template-family">
    <h2 id="template-family" class="text-base font-semibold">{{ __('lynx-admin::templates.family.heading') }}</h2>

    <div class="grid gap-2 text-sm">
        <label class="flex items-center gap-2"><input class="blat-checkbox" id="family-has-url" type="checkbox" wire:model="hasUrl"> {{ __('lynx-admin::templates.family.has_url') }} <span class="text-muted-foreground">{{ __('lynx-admin::templates.family.has_url_note') }}</span></label>
        <label class="flex items-center gap-2"><input class="blat-checkbox" id="family-singleton" type="checkbox" wire:model="singleton"> {{ __('lynx-admin::templates.family.singleton') }}</label>
        <label class="flex items-center gap-2"><input class="blat-checkbox" id="family-allow-children" type="checkbox" wire:model.live="allowChildren"> {{ __('lynx-admin::templates.family.allow_children') }}</label>
    </div>

    @foreach ([
        'childTemplates' => [__('lynx-admin::templates.family.children'), __('lynx-admin::templates.family.any'), $allowChildren],
        'parentTemplates' => [__('lynx-admin::templates.family.parents'), __('lynx-admin::templates.family.any'), true],
        'switchTemplates' => [__('lynx-admin::templates.family.switch'), __('lynx-admin::templates.family.any'), true],
    ] as $property => [$legend, $hint, $isShown])
        @if ($isShown)
            <fieldset class="grid gap-2">
                <legend class="mb-1 text-sm font-medium">{{ $legend }}</legend>
                <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm">
                    @foreach ($others as $option)
                        <label class="flex items-center gap-2"><input class="blat-checkbox" id="family-{{ $property }}-{{ $option }}" type="checkbox" value="{{ $option }}" wire:model="{{ $property }}"> {{ $option }}</label>
                    @endforeach
                </div>
                <p class="text-muted-foreground text-sm">{{ $hint }}</p>
            </fieldset>
        @endif
    @endforeach

    @foreach (['has_url', 'singleton', 'allow_children', 'child_templates', 'parent_templates', 'allowed_templates'] as $key)
        @error($key) <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
    @endforeach
    @if ($canEdit) <div><x-lynx-admin::ui.button variant="outline" type="button" wire:click="saveFamily">{{ __('lynx-admin::templates.family.save') }}</x-lynx-admin::ui.button></div> @endif
</section>

<section class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-3 p-5" aria-labelledby="template-pages">
    <h2 id="template-pages" class="text-base font-semibold">{{ __('lynx-admin::templates.pages.heading') }}</h2>
    <p class="text-sm">{{ trans_choice('lynx-admin::templates.pages.count', $pageCount) }}</p>

    @if ($pagesUsing !== [])
        <ul class="grid gap-1 text-sm">
            @foreach ($pagesUsing as $using)
                <li wire:key="using-{{ $using['id'] }}" class="flex flex-wrap items-baseline gap-x-2">
                    <a href="{{ route('lynx.admin.pages.edit', ['page' => $using['id'], 'tab' => 'settings']) }}" class="hover:underline">{{ $using['title'] }}</a>
                    @if ($using['trashed'])
                        <x-lynx-admin::ui.badge variant="secondary">{{ __('lynx-admin::templates.pages.in_trash') }}</x-lynx-admin::ui.badge>
                    @else
                        <span class="text-muted-foreground font-mono text-xs">{{ $using['url'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
        @if ($pageCount > count($pagesUsing))
            <p class="text-muted-foreground text-sm">{{ __('lynx-admin::templates.pages.more', ['count' => $pageCount - count($pagesUsing)]) }}</p>
        @endif
    @endif

    @error('template') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror

    @if ($pageCount > 0)
        {{-- templates.delete refuses while any page uses the template, trashed ones included. --}}
        <p class="text-muted-foreground text-sm">{{ __('lynx-admin::templates.pages.switch_first') }}</p>
    @elseif ($isConfirmingDelete)
        <div role="alert" class="grid gap-3">
            <ul class="list-disc ps-5 text-sm">
                @foreach ($deleteSummary as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
            <div class="flex flex-wrap gap-3">
                <x-lynx-admin::ui.button variant="destructive" type="button" wire:click="delete">{{ __('lynx-admin::templates.pages.delete_named', ['name' => $template]) }}</x-lynx-admin::ui.button>
                <x-lynx-admin::ui.button variant="outline" type="button" wire:click="cancelDelete">{{ __('lynx-admin::templates.pages.cancel') }}</x-lynx-admin::ui.button>
            </div>
        </div>
    @elseif ($canEdit)
        <div><x-lynx-admin::ui.button variant="outline" type="button" wire:click="confirmDelete" class="text-destructive hover:text-destructive">{{ __('lynx-admin::templates.pages.delete') }}</x-lynx-admin::ui.button></div>
    @endif
</section>
