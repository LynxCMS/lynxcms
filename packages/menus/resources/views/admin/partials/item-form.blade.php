<form wire:submit="saveItem" class="bg-card text-card-foreground rounded-xl border shadow-sm space-y-5 p-5" aria-labelledby="item-form-title">
    <div class="flex items-center justify-between gap-4">
        <h2 id="item-form-title" class="text-base font-semibold">{{ __($item->id ? 'lynx-menus::admin.item.edit' : 'lynx-menus::admin.item.new') }}</h2>
        <button type="button" wire:click="cancelItem" class="text-primary font-medium underline-offset-4 hover:underline text-sm">{{ __('lynx-menus::admin.item.close') }}</button>
    </div>

    {{-- Errors about the item as a whole, e.g. a locked item; page choice errors show by the page field. --}}
    @foreach (['item', ...($item->kind === 'page' ? [] : ['page'])] as $key)
        @error($key) <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
    @endforeach

    <fieldset>
        <legend class="blat-label mb-2">{{ __('lynx-menus::admin.item.kind') }}</legend>
        {{-- BlatUI's segmented look over native radios, so wire:model keeps working. --}}
        <div class="bg-muted text-muted-foreground inline-flex h-9 items-center rounded-lg p-[3px]">
            @foreach (['section' => __('lynx-menus::admin.item.kind_section'), 'page' => __('lynx-menus::admin.item.kind_page'), 'link' => __('lynx-menus::admin.item.kind_link')] as $value => $text)
                <label class="has-checked:bg-background has-checked:text-foreground has-checked:shadow-sm has-focus-visible:ring-ring/50 has-focus-visible:ring-[3px] inline-flex h-full cursor-pointer items-center rounded-md px-3 text-sm font-medium"><input type="radio" class="sr-only" wire:model.live="item.kind" value="{{ $value }}"> {{ $text }}</label>
            @endforeach
        </div>
        @error('kind') <p class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
    </fieldset>

    <div>
        <span id="item-label-label" class="blat-label mb-2">{{ __('lynx-menus::admin.item.label') }}</span>
        @include('lynx-menus::admin.partials.translated-input', ['model' => 'item.label', 'id' => 'item-label', 'label' => __('lynx-menus::admin.item.label'), 'error' => 'label', 'placeholder' => $item->kind === 'page' ? ($item->pageTitle ?: __('lynx-menus::admin.item.page_title')) : ''])
        @if ($item->kind === 'page')
            <p class="text-muted-foreground text-sm mt-1">{{ __(count($languages) > 1 ? 'lynx-menus::admin.item.use_title_languages' : 'lynx-menus::admin.item.use_title') }}</p>
        @endif
        @error('label') <p class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
    </div>

    @if ($item->kind === 'page')
        <div class="space-y-2">
            <label for="page-search" class="blat-label mb-2">{{ __('lynx-menus::admin.item.page') }}</label>
            @if ($item->page)
                <p class="text-sm">{!! __('lynx-menus::admin.item.linked_to', ['page' => '<strong>'.e($item->pageTitle).'</strong>']) !!}</p>
            @endif
            <input id="page-search" type="search" class="blat-input" wire:model.live.debounce.300ms="pageSearch" placeholder="{{ __('lynx-menus::admin.item.search') }}" autocomplete="off" @error('page') aria-invalid="true" @enderror>
            @if ($this->pageResults !== [])
                <ul class="bg-card text-card-foreground rounded-xl border shadow-sm divide-y divide-border overflow-hidden" aria-label="{{ __('lynx-menus::admin.item.matching') }}">
                    @foreach ($this->pageResults as $result)
                        <li>
                            <button type="button" wire:click="choosePage({{ $result['id'] }})" class="flex w-full items-center justify-between gap-4 px-3 py-2 text-start text-sm hover:bg-muted">
                                <span>{{ $result['title'] }}</span>
                                <span class="text-muted-foreground">{{ $result['url'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
            @error('page') <p class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
            <label class="flex items-center gap-2 text-sm"><input class="blat-checkbox" type="checkbox" wire:model.live="item.children"> {{ __('lynx-menus::admin.item.children') }}</label>
            @if ($item->children)
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="item-depth" class="blat-label mb-2">{{ __('lynx-menus::admin.item.levels') }}</label>
                        <input id="item-depth" type="number" min="1" max="{{ \LynxCms\Menus\MenuSchema::MAX_DEPTH }}" class="blat-input" wire:model="item.depth" placeholder="{{ \LynxCms\Menus\MenuSchema::DEFAULT_DEPTH }}" @error('depth') aria-invalid="true" @enderror>
                        <p class="text-muted-foreground text-sm mt-1">{{ __('lynx-menus::admin.item.levels_help', ['max' => \LynxCms\Menus\MenuSchema::MAX_DEPTH]) }}</p>
                        @error('depth') <p class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="item-limit" class="blat-label mb-2">{{ __('lynx-menus::admin.item.limit') }}</label>
                        <input id="item-limit" type="number" min="1" max="{{ \LynxCms\Menus\MenuSchema::MAX_LIMIT }}" class="blat-input" wire:model="item.limit" placeholder="{{ \LynxCms\Menus\MenuSchema::DEFAULT_LIMIT }}" @error('limit') aria-invalid="true" @enderror>
                        <p class="text-muted-foreground text-sm mt-1">{{ __('lynx-menus::admin.item.limit_help') }}</p>
                        @error('limit') <p class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if ($item->kind === 'link')
        <div>
            <span id="item-url-label" class="blat-label mb-2">{{ __('lynx-menus::admin.item.url') }}</span>
            @include('lynx-menus::admin.partials.translated-input', ['model' => 'item.url', 'id' => 'item-url', 'label' => __('lynx-menus::admin.item.url'), 'error' => 'url', 'placeholder' => __('lynx-menus::admin.item.url_placeholder')])
            <p class="text-muted-foreground text-sm mt-1">{{ __('lynx-menus::admin.item.url_help') }}</p>
            @error('url') <p class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>
    @endif

    @if ($item->kind !== 'section')
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="item-target" class="blat-label mb-2">{{ __('lynx-menus::admin.item.open_in') }}</label>
                <select id="item-target" class="blat-select" wire:model.live="item.targetChoice">
                    @foreach (\LynxCms\Menus\Admin\Forms\ItemForm::TARGETS as $value => $text)
                        <option value="{{ $value }}">{{ __('lynx-menus::admin.item.'.$text) }}</option>
                    @endforeach
                </select>
            </div>
            @if ($item->targetChoice === 'custom')
                <div>
                    <label for="item-target-custom" class="blat-label mb-2">{{ __('lynx-menus::admin.item.target_name') }}</label>
                    <input id="item-target-custom" type="text" class="blat-input" wire:model="item.target" placeholder="{{ __('lynx-menus::admin.item.target_placeholder') }}" @error('target') aria-invalid="true" @enderror>
                </div>
            @endif
        </div>
        @error('target') <p class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror

        @if (\LynxCms\Menus\Admin\Forms\ItemForm::relChoices() !== [])
            <fieldset class="grid gap-1.5">
                <legend class="blat-label mb-2">{{ __('lynx-menus::admin.item.relation') }}</legend>
                @foreach (\LynxCms\Menus\Admin\Forms\ItemForm::relChoices() as $value => $text)
                    <label class="flex items-center gap-2 text-sm">
                        <input id="item-rel-{{ $value }}" type="checkbox" class="blat-checkbox" value="{{ $value }}" wire:model="item.rel"> {{ $text }}
                    </label>
                @endforeach
                <p class="text-muted-foreground text-sm">{{ __('lynx-menus::admin.item.noopener') }}</p>
                @error('rel.*') <p class="text-destructive text-sm">{{ $message }}</p> @enderror
            </fieldset>
        @endif
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="item-icon-before" class="blat-label mb-2">{{ __('lynx-menus::admin.item.icon_before') }}</label>
            <input id="item-icon-before" type="text" class="blat-input" wire:model="item.iconBefore" placeholder="{{ __('lynx-menus::admin.item.icon_before_placeholder') }}" @error('icon_before') aria-invalid="true" @enderror>
            @error('icon_before') <p class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="item-icon-after" class="blat-label mb-2">{{ __('lynx-menus::admin.item.icon_after') }}</label>
            <input id="item-icon-after" type="text" class="blat-input" wire:model="item.iconAfter" placeholder="{{ __('lynx-menus::admin.item.icon_after_placeholder') }}" @error('icon_after') aria-invalid="true" @enderror>
            @error('icon_after') <p class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="item-class" class="blat-label mb-2">{{ __('lynx-menus::admin.item.classes') }}</label>
            <input id="item-class" type="text" class="blat-input" wire:model="item.class" placeholder="{{ __('lynx-menus::admin.item.optional') }}" @error('class') aria-invalid="true" @enderror>
            @error('class') <p class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>
    </div>

    @if (count($languages) > 1)
        <fieldset>
            <legend class="blat-label mb-2">{{ __('lynx-menus::admin.item.show_in') }}</legend>
            <div class="flex flex-wrap gap-4">
                @foreach ($languages as $language)
                    <label class="flex items-center gap-2 text-sm"><input class="blat-checkbox" type="checkbox" wire:model="item.languages" value="{{ $language['code'] }}"> {{ $language['name'] }}</label>
                @endforeach
            </div>
            @error('languages') <p class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </fieldset>
    @endif

    <div class="flex gap-3">
        <x-lynx-admin::ui.button type="submit">{{ __('lynx-menus::admin.item.save') }}</x-lynx-admin::ui.button>
        <x-lynx-admin::ui.button variant="outline" type="button" wire:click="cancelItem">{{ __('lynx-menus::admin.item.cancel') }}</x-lynx-admin::ui.button>
    </div>
</form>
