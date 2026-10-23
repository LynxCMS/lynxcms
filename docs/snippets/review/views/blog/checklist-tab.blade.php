<section aria-labelledby="post-checklist" class="bg-card grid max-w-3xl gap-3 rounded-xl border p-5">
    <h2 id="post-checklist" class="font-medium">Ready to publish?</h2>

    @if ($problems === [])
        <p class="text-sm">Yes. This post has everything it needs.</p>
    @else
        <ul class="grid list-disc gap-1 pl-5 text-sm">
            @foreach ($problems as $problem)
                <li>{{ $problem }}</li>
            @endforeach
        </ul>
        <div>
            <x-lynx-admin::ui.button variant="outline" size="sm" wire:click="summarizeFromBody">Summarize from the body</x-lynx-admin::ui.button>
        </div>
    @endif
</section>
