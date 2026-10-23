{{-- Seats and the booking form. An island: the page cache can keep the session page, and every
     visitor still gets the current seat count (chapter 10). --}}
@php($course = $page->parent())
<div class="grid gap-4">
    @if (App\Bookings\Seats::areLeft($page))
        <p class="text-sm"><strong>{{ $page->seats }}</strong> @string('booking.seats', ['en' => 'seats left', 'pt' => 'lugares disponíveis'], scope: 'site')</p>
        <form method="post" action="{{ route('bookings.store', $page->id) }}" class="grid gap-3">
            @csrf
            <input type="hidden" name="language" value="{{ lynx()->languages->current()->code }}">
            <label class="grid gap-1 text-sm">@string('booking.name', ['en' => 'Your name', 'pt' => 'O seu nome'], scope: 'site')
                <x-ui.input name="name" :value="old('name', auth()->user()?->name)" required autocomplete="name" />
            </label>
            <label class="grid gap-1 text-sm">Email
                <x-ui.input type="email" name="email" :value="old('email', auth()->user()?->email)" required autocomplete="email" />
            </label>
            @error('session')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
            <button type="submit" class="atlas-pill-btn atlas-pill-btn-primary">
                @string('booking.pay', ['en' => 'Book and pay', 'pt' => 'Reservar e pagar'], scope: 'site')
                {{ Illuminate\Support\Number::currency((float) (string) ($page->price ?? $course?->price), 'EUR', lynx()->languages->current()->appLocale()) }}
            </button>
        </form>
    @else
        <p class="atlas-muted">@string('booking.closed', ['en' => 'This session is full or no longer open. See the course for other dates.', 'pt' => 'Esta sessão está cheia ou já não está aberta. Veja outras datas no curso.'], scope: 'site')</p>
    @endif
</div>
