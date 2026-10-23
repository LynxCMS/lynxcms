<x-lynx-admin::layout :title="'Results: '.$form->text('label')->in()" :crumbs="['Forms' => route('lynx.admin.forms')]">
    <x-lynx-admin::ui.page-header :title="$form->text('label')->in()" :description="trans_choice(':count response, spam left out|:count responses, spam left out', $results['responses'])" class="mb-6 [&_h1]:text-2xl [&_h1]:font-semibold">
        <x-lynx-admin::ui.button variant="outline" size="sm" :href="route('lynx.admin.forms.export', $form->key())">Export CSV</x-lynx-admin::ui.button>
    </x-lynx-admin::ui.page-header>

    {{-- A form placed on several pages, such as one survey per course, filters by page. --}}
    @if (count($pages) > 1)
        <nav class="mb-4 flex flex-wrap gap-2" aria-label="Sent from">
            <x-lynx-admin::ui.button size="xs" :variant="$page === null ? 'default' : 'outline'" :href="route('lynx.admin.forms.results', $form->key())">All pages</x-lynx-admin::ui.button>
            @foreach ($pages as $pageId => $count)
                <x-lynx-admin::ui.button size="xs" :variant="$page === $pageId ? 'default' : 'outline'" :href="route('lynx.admin.forms.results', [$form->key(), 'page' => $pageId])">
                    {{ lynx()->pages->get($pageId)?->parent()?->title ?? lynx()->pages->get($pageId)?->title ?? "#{$pageId}" }} ({{ $count }})
                </x-lynx-admin::ui.button>
            @endforeach
        </nav>
    @endif

    <div class="grid max-w-3xl gap-4">
        @foreach ($results['questions'] as $question)
            <section class="bg-card grid gap-3 rounded-xl border p-5" aria-labelledby="q-{{ $question['name'] }}">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 id="q-{{ $question['name'] }}" class="font-medium">{{ $question['label'] }}</h2>
                    <span class="text-muted-foreground text-sm">{{ $question['answered'] }} answered</span>
                </div>

                @if (isset($question['options']))
                    <ul class="grid gap-2">
                        @foreach ($question['options'] as $option)
                            @php($share = $question['answered'] > 0 ? round(100 * $option['count'] / max($question['answered'], 1)) : 0)
                            <li class="grid gap-1 text-sm">
                                <span class="flex justify-between"><span>{{ $option['label'] }}</span><span class="text-muted-foreground">{{ $option['count'] }} · {{ $share }}%</span></span>
                                <span class="bg-muted h-2 overflow-hidden rounded-full" aria-hidden="true"><span class="bg-primary block h-full" style="width: {{ $share }}%"></span></span>
                            </li>
                        @endforeach
                    </ul>
                @elseif (isset($question['average']))
                    <p class="text-sm">Average <strong>{{ $question['average'] }}</strong> · lowest {{ $question['lowest'] }} · highest {{ $question['highest'] }}</p>
                @elseif (isset($question['earliest']))
                    <p class="text-sm">From {{ $question['earliest'] }} to {{ $question['latest'] }}</p>
                @elseif (! empty($question['latest']))
                    <ul class="text-muted-foreground grid list-disc gap-1 pl-5 text-sm">
                        @foreach ($question['latest'] as $answer)
                            <li>{{ $answer }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted-foreground text-sm">No answers yet.</p>
                @endif
            </section>
        @endforeach
    </div>
</x-lynx-admin::layout>
