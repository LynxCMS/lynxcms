<form wire:submit="save" class="flex flex-col gap-6">
    @unless ($canEdit)
        <x-lynx-admin::schema-readonly />
    @endunless

    <fieldset class="contents" @disabled(! $canEdit)>

    <section class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-5 p-5" aria-labelledby="language-basics">
        <h2 id="language-basics" class="text-base font-semibold">{{ __('lynx-admin::languages.form.heading') }}</h2>

        <div class="grid gap-1">
            <label for="language-code" class="text-sm font-medium">{{ __('lynx-admin::languages.form.code') }}</label>
            <input id="language-code" type="text" class="blat-input max-w-xs font-mono" wire:model="code" autocomplete="off" @error('code') aria-invalid="true" @enderror>
            <p class="text-muted-foreground text-sm">{!! __('lynx-admin::languages.form.code_help', ['short' => '<code>pt</code>', 'long' => '<code>pt-br</code>']) !!}</p>
            @error('code') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-1">
            <label for="language-name" class="text-sm font-medium">{{ __('lynx-admin::languages.form.name') }}</label>
            <input id="language-name" type="text" class="blat-input max-w-sm" wire:model="name" placeholder="Português" @error('name') aria-invalid="true" @enderror>
            @error('name') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-1">
            <label for="language-label" class="text-sm font-medium">{{ __('lynx-admin::languages.form.tab_label') }}</label>
            <input id="language-label" type="text" class="blat-input max-w-xs" wire:model="label" maxlength="32" placeholder="{{ $name !== '' ? $name : __('lynx-admin::languages.form.from_name') }}">
            <p class="text-muted-foreground text-sm">{{ __('lynx-admin::languages.form.tab_help') }}</p>
            @error('label') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-1">
            <label for="language-locale" class="text-sm font-medium">{{ __('lynx-admin::languages.form.locale') }}</label>
            <input id="language-locale" type="text" class="blat-input max-w-xs font-mono" wire:model="locale" placeholder="{{ $code !== '' ? $code : 'pt-PT' }}">
            <p class="text-muted-foreground text-sm">{!! __('lynx-admin::languages.form.locale_help', ['attribute' => '<code>lang</code>']) !!}</p>
            @error('locale') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-1">
            <label for="language-prefix" class="text-sm font-medium">{{ __('lynx-admin::languages.form.prefix') }}</label>
            <input id="language-prefix" type="text" class="blat-input max-w-xs font-mono" wire:model="prefix" placeholder="{{ $language === null ? ($code !== '' ? $code : 'pt') : '' }}">
            <p class="text-muted-foreground text-sm">{{ __($language === null ? 'lynx-admin::languages.form.prefix_new' : 'lynx-admin::languages.form.prefix_help') }}</p>
            @error('prefix') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
        </div>

        @unless ($isDefault)
            <div class="grid gap-1">
                <label for="language-fallback" class="text-sm font-medium">{{ __('lynx-admin::languages.form.fallback') }}</label>
                <select id="language-fallback" class="blat-select max-w-sm" wire:model="fallback">
                    <option value="">{{ __('lynx-admin::languages.form.default_language') }}</option>
                    @foreach ($fallbacks as $fallbackCode => $fallbackName)
                        <option value="{{ $fallbackCode }}">{{ $fallbackName }} ({{ $fallbackCode }})</option>
                    @endforeach
                </select>
                <p class="text-muted-foreground text-sm">{{ __('lynx-admin::languages.form.fallback_help') }}</p>
                @error('fallback') <p role="alert" class="text-destructive mt-1 text-sm">{{ $message }}</p> @enderror
            </div>
        @endunless

        <label class="flex items-center gap-2 text-sm"><input id="language-active" class="blat-checkbox" type="checkbox" wire:model="active"> {{ __('lynx-admin::languages.form.active') }}</label>
    </section>
    </fieldset>

    <div class="flex flex-wrap items-center gap-3">
        @if ($canEdit)
            <x-lynx-admin::ui.button type="submit">{{ __($language === null ? 'lynx-admin::languages.form.create' : 'lynx-admin::languages.form.save') }}</x-lynx-admin::ui.button>
        @endif
        <x-lynx-admin::ui.button variant="outline" :href="route('lynx.admin.languages')">{{ __('lynx-admin::languages.form.back') }}</x-lynx-admin::ui.button>
        @if ($canEdit && $language !== null && ! $isDefault && ! $isConfirmingDelete)
            <x-lynx-admin::ui.button variant="outline" type="button" wire:click="confirmDelete" class="ms-auto text-destructive hover:text-destructive">{{ __('lynx-admin::languages.form.delete') }}</x-lynx-admin::ui.button>
        @endif
    </div>

    @if ($isConfirmingDelete)
        <section role="alert" class="bg-card text-card-foreground rounded-xl border shadow-sm grid gap-3 p-5" aria-labelledby="language-delete">
            <h2 id="language-delete" class="text-base font-semibold text-destructive">{{ __('lynx-admin::languages.form.confirm', ['name' => $language]) }}</h2>
            <ul class="list-disc ps-5 text-sm">
                @foreach ($deleteSummary as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
            <p class="text-muted-foreground text-sm">{{ __('lynx-admin::languages.form.delete_help') }}</p>
            <div class="flex flex-wrap gap-3">
                <x-lynx-admin::ui.button variant="destructive" type="button" wire:click="delete">{{ __('lynx-admin::languages.form.delete_named', ['name' => $language]) }}</x-lynx-admin::ui.button>
                <x-lynx-admin::ui.button variant="outline" type="button" wire:click="cancelDelete">{{ __('lynx-admin::languages.form.cancel') }}</x-lynx-admin::ui.button>
            </div>
        </section>
    @endif
</form>
