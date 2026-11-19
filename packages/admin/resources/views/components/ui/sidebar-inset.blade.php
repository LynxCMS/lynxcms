{{-- A div, not BlatUI's <main>: the layout's own <main> inside it holds only the screen, not the top bar. --}}
<div data-slot="sidebar-inset" {{ $attributes->twMerge('bg-background relative flex w-full flex-1 flex-col md:peer-data-[variant=inset]:m-2 md:peer-data-[variant=inset]:ms-0 md:peer-data-[variant=inset]:rounded-xl md:peer-data-[variant=inset]:shadow-sm') }}>
    {{ $slot }}
</div>
