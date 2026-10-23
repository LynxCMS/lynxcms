<aside class="atlas-clay h-fit p-6" aria-labelledby="sessions">
    <p class="atlas-figure">{{ Illuminate\Support\Number::currency((float) $page->price, 'EUR', lynx()->languages->current()->appLocale()) }}</p>
    <p class="atlas-muted text-sm">{{ $page->hours }} h</p>
    <h2 id="sessions" class="mt-6 text-lg">@string('course.sessions', ['en' => 'Upcoming dates', 'pt' => 'Próximas datas'])</h2>
    <ul class="mt-3 grid gap-3">
        @forelse ($page->children('template=session, starts>=now, sort=starts') as $session)
            <li>
                <a href="{{ $session->url }}" class="atlas-session">
                    <span class="font-semibold">{{ $session->starts->locale(lynx()->languages->current()->appLocale())->isoFormat('D MMM YYYY, HH:mm') }}</span>
                    <span class="atlas-muted text-sm">{{ $session->location?->title }}</span>
                    @if ($session->availability?->value === 'cancelled')
                        <x-ui.badge variant="destructive">{{ $session->availability }}</x-ui.badge>
                    @endif
                </a>
            </li>
        @empty
            <li class="atlas-muted text-sm">@string('course.no-sessions', ['en' => 'New dates soon.', 'pt' => 'Novas datas em breve.'])</li>
        @endforelse
    </ul>
</aside>
