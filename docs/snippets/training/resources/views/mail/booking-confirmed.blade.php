<x-mail::message>
# {{ __('Your place is confirmed.') }}

{{ __('Hello :name,', ['name' => $booking->name]) }}

**{{ $course }}**

- {{ __('When') }}: {{ $when }}
- {{ __('Where') }}: {{ $where }}
- {{ __('Paid') }}: {{ Illuminate\Support\Number::currency($booking->amount / 100, strtoupper($booking->currency), $booking->language) }}

{{ __('See you there!') }}<br>
Atlas Training
</x-mail::message>
