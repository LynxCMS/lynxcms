{{--
    The fields of the current editor tab, as the template lays them out (TemplateLayout): a main
    and an optional sidebar column of fields and sections. Consecutive fields share one card and sit side by side by their width (ADR-0063).
    Without a layout, every field is one list, as before layouts existed.
--}}
@php
    $views = collect($fields)->keyBy('name');
    // Hidden fields never show in the editor (ADR-0063).
    $isShown = fn (string $name): bool => $views->has($name) && $views[$name]->display !== \LynxCms\Core\Templates\FieldDisplay::Hidden && ($states[$name]['shown'] ?? true);
    $errorFields = collect($errors->keys())->filter(fn ($key) => str_starts_with($key, 'values.'))->map(fn ($key) => explode('.', $key)[1] ?? '')->all();
@endphp
@if (! $layout->hasLayout())
    <section class="@container bg-card text-card-foreground min-w-0 rounded-xl border shadow-sm flex flex-wrap overflow-hidden" aria-label="Content">
        @foreach ($fields as $field)
            @continue(! $isShown($field->name))
            @include('lynx-admin::livewire.partials.editor-field')
        @endforeach
    </section>
@else
    <div @class(['grid items-start gap-6', 'lg:grid-cols-[minmax(0,1fr)_minmax(16rem,22rem)]' => $layout->hasSidebar($tab)])>
        @foreach (['main' => 'Main', 'sidebar' => 'Sidebar'] as $column => $columnLabel)
            @continue($column === 'sidebar' && ! $layout->hasSidebar($tab))
            @php
                // Consecutive loose fields share a card; each section is its own card.
                $groups = [];
                foreach ($layout->blocks($tab, $column) as $block) {
                    if ($block->isSection()) {
                        $groups[] = $block;
                    } elseif ($isShown($block->field)) {
                        is_array(end($groups)) ? $groups[array_key_last($groups)][] = $block->field : $groups[] = [$block->field];
                    }
                }
            @endphp
            <div class="grid min-w-0 content-start gap-6" role="group" aria-label="{{ $columnLabel }}">
                @foreach ($groups as $group)
                    @if (is_array($group))
                        <section class="@container bg-card text-card-foreground min-w-0 rounded-xl border shadow-sm flex flex-wrap overflow-hidden">
                            @foreach ($group as $name)
                                @include('lynx-admin::livewire.partials.editor-field', ['field' => $views[$name]])
                            @endforeach
                        </section>
                    @else
                        @include('lynx-admin::livewire.partials.editor-section', ['block' => $group])
                    @endif
                @endforeach
            </div>
        @endforeach
    </div>
@endif
