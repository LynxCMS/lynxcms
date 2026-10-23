{{-- The members area. Fortify sends members here after they sign in (config/fortify.php "home"). --}}
<x-layouts.site :page="$page" :seo="['robots' => 'noindex, nofollow']">
    <section class="px-4 py-12">
        <div class="mx-auto max-w-3xl">
            <h1 class="atlas-section-title">@string('account.title', ['en' => 'Your bookings', 'pt' => 'As suas reservas'], scope: 'site')</h1>
            <p class="atlas-lead mt-2">{{ auth()->user()->name }}</p>

            <ul class="mt-8 grid gap-4">
                @forelse ($bookings as $booking)
                    @php($session = $booking->session())
                    <li class="atlas-clay flex flex-wrap items-center justify-between gap-3 p-5">
                        <div>
                            <p class="font-semibold">@field($session?->title)</p>
                            <p class="atlas-muted text-sm">@field($session?->location?->title)</p>
                        </div>
                        @if ($booking->isPaid() && ($materials = $session?->parent()?->children('template=materials')->first()))
                            <a href="{{ $materials->url }}" class="atlas-pill-btn atlas-pill-btn-outline" style="padding:.5rem 1.25rem;font-size:.9rem">@field($materials->title)</a>
                        @else
                            <x-ui.badge class="rounded-full atlas-badge">{{ $booking->status }}</x-ui.badge>
                        @endif
                    </li>
                @empty
                    <li class="atlas-muted">@string('account.none', ['en' => 'No bookings yet. Book a session while signed in, and it appears here.', 'pt' => 'Ainda não tem reservas. Reserve uma sessão com sessão iniciada, e ela aparece aqui.'], scope: 'site')</li>
                @endforelse
            </ul>

            <form method="post" action="{{ route('logout') }}" class="mt-10">
                @csrf
                <button type="submit" class="atlas-muted text-sm underline">@string('account.sign-out', ['en' => 'Sign out', 'pt' => 'Terminar sessão'], scope: 'site')</button>
            </form>
        </div>
    </section>
</x-layouts.site>
