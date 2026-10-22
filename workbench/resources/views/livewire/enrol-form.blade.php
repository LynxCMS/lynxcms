<form wire:submit="save" class="enrol-form">
    @if ($sent)
        <p>{{ __('Thank you!') }} {{ $course?->title }}</p>
    @elseif ($isFull)
        <p>{{ $course?->title }}: {{ __('Fully booked') }}</p>
    @else
        <p>{{ $course?->title }}</p>
        <input type="email" wire:model="email">
        @error('email') <span class="error">{{ $message }}</span> @enderror
        <button type="submit">{{ __('Enrol') }}</button>
    @endif
</form>
