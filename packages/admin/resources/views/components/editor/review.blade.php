@props(['entries', 'draft' => false])
{{-- What a save would change (ChangeReview entries), with the actions in the slot. Reused for agent plans.
     draft: what the page's draft would change on the live page once published. Rich text shows formatted,
     cleaned as a save would clean it; "Show HTML" switches every rich text value to its markup. --}}
@php($hasHtml = collect($entries)->contains(fn ($entry) => $entry->format === 'html'))
<section class="bg-card text-card-foreground rounded-xl border shadow-sm p-5" aria-labelledby="review-heading" @if ($draft) data-review-draft @endif x-data="{ raw: false }">
    <div class="mb-1 flex flex-wrap items-baseline justify-between gap-3">
        <h2 id="review-heading" class="text-base font-semibold">{{ __($draft ? 'lynx-admin::editor.review.draft_title' : 'lynx-admin::editor.review.title') }}</h2>
        @if ($hasHtml)
            <button type="button" data-review-raw-toggle x-on:click="raw = ! raw" x-bind:aria-pressed="raw ? 'true' : 'false'"
                    class="text-primary text-sm underline-offset-4 hover:underline"
                    x-text="raw ? @js(__('lynx-admin::editor.review.show_formatted')) : @js(__('lynx-admin::editor.review.show_html'))">{{ __('lynx-admin::editor.review.show_html') }}</button>
        @endif
    </div>
    <p class="text-muted-foreground text-sm mb-4">{{ trans_choice($draft ? 'lynx-admin::editor.review.draft_intro' : 'lynx-admin::editor.review.intro', count($entries)) }}</p>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="text-muted-foreground">
                <tr class="border-b border-border">
                    <th scope="col" class="py-2 pe-4 font-medium">{{ __('lynx-admin::editor.review.field') }}</th>
                    <th scope="col" class="py-2 pe-4 font-medium">{{ __('lynx-admin::editor.review.before') }}</th>
                    <th scope="col" class="py-2 font-medium">{{ __('lynx-admin::editor.review.after') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border align-top">
                @foreach ($entries as $entry)
                    <tr>
                        <th scope="row" class="py-2 pe-4 font-medium">
                            {{ $entry->label }}
                            @if ($entry->language) <span class="block font-normal text-muted-foreground">{{ $entry->language }}</span> @endif
                        </th>
                        <td class="py-2 pe-4 text-muted-foreground">
                            @if ($entry->before === null || $entry->before === '' || $entry->before === []) <span class="text-muted-foreground text-sm">{{ __('lynx-admin::editor.review.empty') }}</span>
                            @elseif ($entry->format === 'html' && is_string($entry->before))
                                <div class="lynx-richtext-content" x-show="! raw">{!! app(\LynxCms\Core\Fields\HtmlCleaner::class)->clean($entry->before) !!}</div>
                                <pre class="lynx-code whitespace-pre-wrap" x-show="raw" x-cloak>{{ $entry->before }}</pre>
                            @else <x-lynx-admin::editor.value :type="$entry->type" :value="$entry->before" /> @endif
                        </td>
                        <td class="py-2">
                            @if ($entry->after === null || $entry->after === '' || $entry->after === []) <span class="text-muted-foreground text-sm">{{ __('lynx-admin::editor.review.empty') }}</span>
                            @elseif ($entry->format === 'html' && is_string($entry->after))
                                <div class="lynx-richtext-content" x-show="! raw">{!! app(\LynxCms\Core\Fields\HtmlCleaner::class)->clean($entry->after) !!}</div>
                                <pre class="lynx-code whitespace-pre-wrap" x-show="raw" x-cloak>{{ $entry->after }}</pre>
                            @else <x-lynx-admin::editor.value :type="$entry->type" :value="$entry->after" /> @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4 flex flex-wrap gap-3">{{ $slot }}</div>
</section>
